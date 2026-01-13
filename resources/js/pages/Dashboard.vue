<script setup>
import { onMounted, ref } from 'vue';
import MapView from '../components/MapView.vue';

const loading = ref(false);
const observedAt = ref(null);
const items = ref([]);

async function fetchLatest() {
  loading.value = true;
  try {
    const res = await fetch('/api/risk/latest');
    const data = await res.json();
    observedAt.value = data.observed_at;
    items.value = data.items || [];
  } finally {
    loading.value = false;
  }
}

async function ingest() {
  loading.value = true;
  try {
    await fetch('/api/dummy/ingest', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}' });
    await fetchLatest();
  } finally {
    loading.value = false;
  }
}

function badgeClass(level) {
  if (level === 'LOW') return 'badge low';
  if (level === 'MEDIUM') return 'badge medium';
  return 'badge high';
}

onMounted(fetchLatest);
</script>

<template>
  <div class="grid">
    <div class="card">
      <div class="row" style="justify-content: space-between">
        <div>
          <h1 class="h1">Dashboard Risiko (Dummy)</h1>
          <div class="muted">Update: <b>{{ observedAt || '-' }}</b></div>
        </div>
        <div class="row">
          <button class="btn2" @click="fetchLatest" :disabled="loading">Refresh</button>
          <button class="btn" @click="ingest" :disabled="loading">Generate data baru</button>
        </div>
      </div>

      <div style="margin-top:12px">
        <MapView :items="items" />
      </div>

      <div style="margin-top:14px" class="muted">
        Catatan: data ini random. Normalisasi & threshold bisa kamu edit di <code>config/risk.php</code>.
      </div>
    </div>

    <div class="card">
      <h1 class="h1">Tabel Risiko</h1>
      <table class="table">
        <thead>
          <tr>
            <th>Area</th>
            <th>Index</th>
            <th>Level</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in items" :key="r.area_id">
            <td>
              <div style="font-weight:700">{{ r.area_name }}</div>
              <div class="muted small">BMKG {{ r.detail?.scores?.BMKG_RAIN ?? '-' }} | InaRISK {{ r.detail?.scores?.INARISK ?? '-' }} | Sea {{ r.detail?.scores?.SEA_LEVEL ?? '-' }}</div>
            </td>
            <td>{{ (r.risk_index ?? 0).toFixed(3) }}</td>
            <td><span :class="badgeClass(r.risk_level)">{{ r.risk_level }}</span></td>
          </tr>
        </tbody>
      </table>

      <div class="muted" style="margin-top:12px">
        Kamu bisa ubah bobot kriteria di menu <b>Fuzzy AHP</b>.
      </div>
    </div>
  </div>
</template>
