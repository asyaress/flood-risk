<template>
  <div>
    <div class="nav">
      <div class="brand">Flood Risk Samarinda (Dummy)</div>
      <div style="flex:1"></div>
      <RouterLink to="/" class="pill" :class="{active: $route.path==='/' }">Dashboard</RouterLink>
      <RouterLink to="/ahp" class="pill" :class="{active: $route.path==='/ahp' }">Fuzzy AHP</RouterLink>
      <a class="pill" href="/api/risk/latest" target="_blank">API</a>
    </div>

    <div class="container">
      <RouterView />
    </div>
  </div>
</template>
