<script setup>
import { onMounted, watch, ref } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const props = defineProps({
  items: { type: Array, default: () => [] }
});

const mapRef = ref(null);
let map = null;
let markersLayer = null;

function color(level) {
  if (level === 'LOW') return '#7CFFB2';
  if (level === 'MEDIUM') return '#FFE08A';
  return '#FF8AA1';
}

function renderMarkers() {
  if (!map || !markersLayer) return;
  markersLayer.clearLayers();

  for (const r of props.items) {
    if (typeof r.lat !== 'number' || typeof r.lng !== 'number') continue;

    const marker = L.circleMarker([r.lat, r.lng], {
      radius: 9,
      weight: 2,
      color: color(r.risk_level),
      fillOpacity: 0.7,
    });

    marker.bindPopup(`
      <b>${r.area_name}</b><br/>
      Risk: ${Number(r.risk_index).toFixed(3)} (${r.risk_level})<br/>
      BMKG: ${r.detail?.scores?.BMKG_RAIN ?? '-'} | InaRISK: ${r.detail?.scores?.INARISK ?? '-'} | Sea: ${r.detail?.scores?.SEA_LEVEL ?? '-'}
    `);

    marker.addTo(markersLayer);
  }
}

onMounted(() => {
  map = L.map('map', { zoomControl: true }).setView([-0.502, 117.153], 12);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 18,
    attribution: '© OpenStreetMap'
  }).addTo(map);
  markersLayer = L.layerGroup().addTo(map);
  renderMarkers();
});

watch(() => props.items, () => renderMarkers(), { deep: true });
</script>

<template>
  <div id="map" ref="mapRef"></div>
</template>
