import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Damit axios-Requests automatisch den XSRF-TOKEN-Cookie mitschicken
// (Laravel Sanctum/Session-CSRF-Schutz für unsere PATCH/POST-Routen).
window.axios.defaults.withCredentials = true;
