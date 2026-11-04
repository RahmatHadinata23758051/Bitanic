import './bootstrap';
import Alpine from 'alpinejs';
import ApexCharts from 'apexcharts';
import { createIcons, icons } from 'lucide';

window.Alpine = Alpine;
window.ApexCharts = ApexCharts;
window.createIcons = createIcons;
window.lucideIcons = icons;

// Inisialisasi Lucide Icons secara otomatis
document.addEventListener('DOMContentLoaded', () => {
    createIcons({ icons });
});

// Re-render icons saat Alpine selesai update DOM
document.addEventListener('alpine:initialized', () => {
    createIcons({ icons });
});

Alpine.start();
