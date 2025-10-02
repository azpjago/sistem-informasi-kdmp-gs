const CACHE_NAME = 'kdmpgs-anggota-v1';
const urlsToCache = [
  './',
  './beranda.php',
  './keranjang.php',
  './kalkulator.php',
  './rincian_pesanan.php',
  './faktur.php',
  './css/styles.css',
  './js/app.js',
  './icon-192x192.png'
];

// Install Service Worker
self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then(cache => {
        return cache.addAll(urlsToCache);
      })
  );
});

// Fetch Event
self.addEventListener('fetch', event => {
  // Jangan cache request PHP yang dinamis
  if (event.request.url.includes('proses_login.php') || 
      event.request.url.includes('simpan_pemesanan.php') ||
      event.request.url.includes('get_riwayat.php')) {
    return fetch(event.request);
  }

  event.respondWith(
    caches.match(event.request)
      .then(response => {
        return response || fetch(event.request);
      })
  );
});

// Background Sync untuk pesanan offline
self.addEventListener('sync', event => {
  if (event.tag === 'sync-pesanan') {
    event.waitUntil(syncPesananOffline());
  }
});

async function syncPesananOffline() {
  // Implementasi sync untuk pesanan offline
  const offlinePesanan = await getOfflinePesanan();
  for (const pesanan of offlinePesanan) {
    try {
      await sendPesananToServer(pesanan);
      await removeOfflinePesanan(pesanan.id);
    } catch (error) {
      console.error('Gagal sync pesanan:', error);
    }
  }
}