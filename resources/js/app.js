// Tambahkan kode ini di akhir file resources/js/app.js

import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';

// Membuat Calendar dan plugin-nya tersedia secara global agar bisa diakses oleh Alpine.js
window.Calendar = Calendar;
window.dayGridPlugin = dayGridPlugin;
