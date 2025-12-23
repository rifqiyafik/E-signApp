import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const storedAuth = window.localStorage.getItem('esign_auth');
if (storedAuth) {
    try {
        const parsed = JSON.parse(storedAuth);
        if (parsed?.accessToken) {
            window.axios.defaults.headers.common.Authorization = `Bearer ${parsed.accessToken}`;
        }
    } catch (error) {
        console.warn('Invalid auth data in localStorage', error);
    }
}
