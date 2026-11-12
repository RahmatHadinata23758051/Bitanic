import './bootstrap';
import Alpine from 'alpinejs';
import ApexCharts from 'apexcharts';
import { createIcons, icons } from 'lucide';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

window.Alpine = Alpine;
window.ApexCharts = ApexCharts;
window.createIcons = createIcons;
window.lucideIcons = icons;
window.L = L;

// Inisialisasi Lucide Icons secara otomatis
document.addEventListener('DOMContentLoaded', () => {
    createIcons({ icons });
});

// Re-render icons saat Alpine selesai update DOM
document.addEventListener('alpine:initialized', () => {
    createIcons({ icons });
});

Alpine.start();
