<?php

namespace App\Services;

use App\Models\Criteria;
use App\Models\PairwiseComparison;

class FuzzyAHPService
{
    /**
     * Reciprocal of a TFN (l,m,u) -> (1/u, 1/m, 1/l)
     */
    private function reciprocal(array $tfn): array
    {
        $l = (float)($tfn['l'] ?? 1.0);
        $m = (float)($tfn['m'] ?? 1.0);
        $u = (float)($tfn['u'] ?? 1.0);

        // Guard against division by zero / invalid inputs.
        $l = $l <= 0 ? 1e-9 : $l;
        $m = $m <= 0 ? 1e-9 : $m;
        $u = $u <= 0 ? 1e-9 : $u;

        return ['l' => 1.0 / $u, 'm' => 1.0 / $m, 'u' => 1.0 / $l];
    }

    /**
     * Sanitize TFN: ensure positive and ordered (l <= m <= u).
     */
    private function sanitizeTfn(array $tfn): array
    {
        $l = max(1e-9, (float)($tfn['l'] ?? 1.0));
        $m = max(1e-9, (float)($tfn['m'] ?? 1.0));
        $u = max(1e-9, (float)($tfn['u'] ?? 1.0));

        // Enforce ordering in case inputs are swapped.
        $vals = [$l, $m, $u];
        sort($vals);
        return ['l' => $vals[0], 'm' => $vals[1], 'u' => $vals[2]];
    }

    /**
     * Build full fuzzy matrix (l,m,u) from stored pairwise comparisons.
     * Returns: [criteriaIds, matrixTFN]
     */
    public function buildMatrix(int $expertId = 1): array
    {
        $criteria = Criteria::query()->whereNull('parent_id')->orderBy('id')->get();
        $ids = $criteria->pluck('id')->all();
        $n = count($ids);

        // Init diagonal
        $matrix = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $matrix[$i][$j] = ['l' => 1.0, 'm' => 1.0, 'u' => 1.0];
            }
        }

        $comparisons = PairwiseComparison::query()
            ->where('expert_id', $expertId)
            ->get()
            ->keyBy(fn($c) => $c->criteria_i_id.'-'.$c->criteria_j_id);

        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                if ($i === $j) continue;

                $ci = $ids[$i];
                $cj = $ids[$j];

                $key = $ci.'-'.$cj;
                $revKey = $cj.'-'.$ci;

                if ($comparisons->has($key)) {
                    $c = $comparisons->get($key);
                    $matrix[$i][$j] = $this->sanitizeTfn(['l' => $c->l, 'm' => $c->m, 'u' => $c->u]);
                } elseif ($comparisons->has($revKey)) {
                    // reciprocal
                    $c = $comparisons->get($revKey);
                    $matrix[$i][$j] = $this->reciprocal($this->sanitizeTfn(['l' => $c->l, 'm' => $c->m, 'u' => $c->u]));
                }
            }
        }

        return [$ids, $matrix];
    }

    /**
     * Compute fuzzy weights via Fuzzy Geometric Mean.
     * Input matrix: [[['l'=>..,'m'=>..,'u'=>..], ...], ...]
     * Returns: ['fuzzy' => [i => ['l','m','u']], 'crisp' => [i => w], 'cr' => float]
     */
    public function computeWeights(array $matrix): array
    {
        $n = count($matrix);
        if ($n < 2) {
            return ['fuzzy' => [], 'crisp' => [], 'cr' => 0.0];
        }

        $g = [];
        $sum = ['l' => 0.0, 'm' => 0.0, 'u' => 0.0];

        for ($i = 0; $i < $n; $i++) {
            // Using log-sum to avoid overflow if you later increase criteria count.
            $logProd = ['l' => 0.0, 'm' => 0.0, 'u' => 0.0];
            for ($j = 0; $j < $n; $j++) {
                $t = $this->sanitizeTfn($matrix[$i][$j]);
                $logProd['l'] += log($t['l']);
                $logProd['m'] += log($t['m']);
                $logProd['u'] += log($t['u']);
            }
            $gm = [
                'l' => exp($logProd['l'] / $n),
                'm' => exp($logProd['m'] / $n),
                'u' => exp($logProd['u'] / $n),
            ];
            $g[$i] = $gm;
            $sum['l'] += $gm['l'];
            $sum['m'] += $gm['m'];
            $sum['u'] += $gm['u'];
        }

        // inverse of sum TFN
        $invSum = [
            'l' => 1.0 / $sum['u'],
            'm' => 1.0 / $sum['m'],
            'u' => 1.0 / $sum['l'],
        ];

        $weightsFuzzy = [];
        $weightsCrisp = [];

        for ($i = 0; $i < $n; $i++) {
            $w = [
                'l' => $g[$i]['l'] * $invSum['l'],
                'm' => $g[$i]['m'] * $invSum['m'],
                'u' => $g[$i]['u'] * $invSum['u'],
            ];
            $w = $this->sanitizeTfn($w);
            $weightsFuzzy[$i] = $w;

            // Defuzzification (centroid) then normalized later.
            $weightsCrisp[$i] = ($w['l'] + $w['m'] + $w['u']) / 3.0;
        }

        // normalize crisp
        $total = array_sum($weightsCrisp);
        if ($total > 0) {
            foreach ($weightsCrisp as $i => $val) {
                $weightsCrisp[$i] = $val / $total;
            }
        }

        // CR from crisp matrix using midpoints m (common practice for fuzzy AHP).
        $cr = $this->consistencyRatio($this->crispMatrixMid($matrix));

        return [
            'fuzzy' => $weightsFuzzy,
            'crisp' => $weightsCrisp,
            'cr' => $cr,
        ];
    }

    public function crispMatrixMid(array $matrix): array
    {
        $n = count($matrix);
        $a = [];
        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < $n; $j++) {
                $t = $this->sanitizeTfn($matrix[$i][$j]);
                $a[$i][$j] = (float)$t['m'];
            }
        }
        return $a;
    }

    public function consistencyRatio(array $a): float
    {
        $n = count($a);
        if ($n < 3) return 0.0;

        // power iteration for principal eigenvector
        $v = array_fill(0, $n, 1.0);
        for ($iter = 0; $iter < 50; $iter++) {
            $av = array_fill(0, $n, 0.0);
            for ($i = 0; $i < $n; $i++) {
                $sum = 0.0;
                for ($j = 0; $j < $n; $j++) {
                    $sum += $a[$i][$j] * $v[$j];
                }
                $av[$i] = $sum;
            }
            $norm = max(array_map('abs', $av));
            if ($norm == 0) break;
            for ($i = 0; $i < $n; $i++) {
                $v[$i] = $av[$i] / $norm;
            }
        }

        // Rayleigh quotient for lambda_max
        $av = array_fill(0, $n, 0.0);
        for ($i = 0; $i < $n; $i++) {
            $sum = 0.0;
            for ($j = 0; $j < $n; $j++) {
                $sum += $a[$i][$j] * $v[$j];
            }
            $av[$i] = $sum;
        }
        $num = 0.0;
        $den = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $num += $v[$i] * $av[$i];
            $den += $v[$i] * $v[$i];
        }
        $lambdaMax = $den == 0 ? $n : ($num / $den);

        $ci = ($lambdaMax - $n) / ($n - 1);

        // Saaty Random Index (RI)
        $riTable = [
            1 => 0.00,
            2 => 0.00,
            3 => 0.58,
            4 => 0.90,
            5 => 1.12,
            6 => 1.24,
            7 => 1.32,
            8 => 1.41,
            9 => 1.45,
            10 => 1.49,
            11 => 1.51,
            12 => 1.48,
            13 => 1.56,
            14 => 1.57,
            15 => 1.59,
        ];
        $ri = $riTable[$n] ?? 1.49;
        if ($ri == 0) return 0.0;

        return max(0.0, $ci / $ri);
    }
}
