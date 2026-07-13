import './bootstrap';

import Alpine from 'alpinejs';
import { registerToastStore } from './toast-store';

window.Alpine = Alpine;

registerToastStore(Alpine);

Alpine.start();
