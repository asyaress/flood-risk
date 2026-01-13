<script setup>
import { computed, onMounted, ref } from 'vue';

const criteria = ref([]);
const matrix = ref([]);
const saving = ref(false);
const computing = ref(false);
const result = ref(null);

function tfnToLabel(tfn) {
  const l = Number(tfn.l).toFixed(2);
  const m = Number(tfn.m).toFixed(2);
  const u = Number(tfn.u).toFixed(2);
  return `(${l}, ${m}, ${u})`;
}

const linguisticOptions = [
  { label: '1 – Sama penting', l: 1, m: 1, u: 1 },
  { label: '3 – Lebih penting', l: 2, m: 3, u: 4 },
  { label: '5 – Sangat penting', l: 4, m: 5, u: 6 },
  { label: '7 – Jauh lebih penting', l: 6, m: 7, u: 8 },
  { label: '9 – Mutlak', l: 9, m: 9, u: 9 },
];

const pairs = computed(() => {
  const out = [];
  for (let i = 0; i < criteria.value.length; i++) {
    for (let j = i + 1; j < criteria.value.length; j++) {
      const ci = criteria.value[i];
      const cj = criteria.value[j];

      // matrix indices follow criteria order
      const tfn = matrix.value?.[i]?.[j] || { l: 1, m: 1, u: 1 };
      out.push({
        i,
        j,
        criteria_i_id: ci.id,
        criteria_j_id: cj.id,
        ci,
        cj,
        tfn: { ...tfn },
      });
    }
  }
  return out;
});

function getSelectedIndex(p) {
  // match by midpoint (m)
  const m = Number(p.tfn.m);
  const idx = linguisticOptions.findIndex(o => Number(o.m) === m);
  return idx >= 0 ? idx : 0;
}

function setOption(p, idx) {
  const opt = linguisticOptions[idx];
  // update matrix i,j
  matrix.value[p.i][p.j] = { l: opt.l, m: opt.m, u: opt.u };
  // reciprocal at j,i
  matrix.value[p.j][p.i] = { l: 1/opt.u, m: 1/opt.m, u: 1/opt.l };
}

async function load() {
  const res = await fetch('/api/ahp/matrix?expert_id=1', {
    headers: { 'Accept': 'application/json' },
  });
  if (!res.ok) {
    const text = await res.text();
    throw new Error(`API /api/ahp/matrix gagal (${res.status}): ${text.slice(0, 200)}`);
  }
  const data = await res.json();
  criteria.value = data.criteria;
  matrix.value = data.matrix;
}

async function save() {
  saving.value = true;
  result.value = null;
  try {
    const items = pairs.value.map(p => ({
      criteria_i_id: p.criteria_i_id,
      criteria_j_id: p.criteria_j_id,
      l: matrix.value[p.i][p.j].l,
      m: matrix.value[p.i][p.j].m,
      u: matrix.value[p.i][p.j].u,
    }));

    await fetch('/api/ahp/matrix', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ expert_id: 1, items }),
    });
  } finally {
    saving.value = false;
  }
}

async function computeWeights() {
  computing.value = true;
  try {
    await save();
    const res = await fetch('/api/ahp/compute', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ expert_id: 1 }),
    });
    if (!res.ok) {
      const text = await res.text();
      throw new Error(`API /api/ahp/compute gagal (${res.status}): ${text.slice(0, 200)}`);
    }
    result.value = await res.json();
  } finally {
    computing.value = false;
  }
}

onMounted(load);
</script>

<template>
  <div class="card">
    <div class="row" style="justify-content: space-between">
      <div>
        <h1 class="h1">Fuzzy AHP (Sederhana)</h1>
        <div class="muted">Isi pairwise antar kriteria → simpan → hitung bobot.</div>
      </div>
      <div class="row">
        <button class="btn2" @click="load" :disabled="saving || computing">Reload</button>
        <button class="btn2" @click="save" :disabled="saving || computing">Simpan</button>
        <button class="btn" @click="computeWeights" :disabled="saving || computing">Hitung Bobot</button>
      </div>
    </div>

    <div style="margin-top:12px" class="grid">
      <div class="card" style="padding:12px">
        <div class="muted" style="margin-bottom:10px">Pairwise input (linguistik)</div>

        <div v-for="p in pairs" :key="p.criteria_i_id + '-' + p.criteria_j_id" style="margin-bottom:12px">
          <div style="font-weight:700; margin-bottom:6px">{{ p.ci.name }} vs {{ p.cj.name }}</div>
          <select class="select" :value="getSelectedIndex(p)" @change="e => setOption(p, Number(e.target.value))">
            <option v-for="(opt, idx) in linguisticOptions" :key="idx" :value="idx">{{ opt.label }}</option>
          </select>
          <div class="muted small" style="margin-top:6px">TFN: {{ tfnToLabel(matrix[p.i][p.j]) }}</div>
        </div>

        <div class="muted small">
          Catatan: untuk kebalikannya otomatis dihitung reciprocal (1/u, 1/m, 1/l).
        </div>
      </div>

      <div class="card" style="padding:12px">
        <div class="muted" style="margin-bottom:10px">Hasil</div>

        <div v-if="!result" class="muted">Klik <b>Hitung Bobot</b> untuk menghasilkan weights + CR.</div>

        <div v-else>
          <div class="row" style="align-items: baseline; gap:12px">
            <div style="font-weight:800">Version: {{ result.version }}</div>
            <div class="muted">CR: <b>{{ Number(result.cr).toFixed(4) }}</b> (target &lt; 0.1)</div>
          </div>

          <table class="table" style="margin-top:10px">
            <thead>
              <tr><th>Kriteria</th><th>Bobot</th></tr>
            </thead>
            <tbody>
              <tr v-for="w in result.weights" :key="w.code">
                <td>
                  <div style="font-weight:700">{{ w.name }}</div>
                  <div class="muted small">{{ w.code }}</div>
                </td>
                <td>{{ Number(w.weight).toFixed(4) }}</td>
              </tr>
            </tbody>
          </table>

          <div class="muted small" style="margin-top:10px">
            Setelah bobot berubah, dashboard akan pakai bobot terbaru otomatis (versi paling tinggi).
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
