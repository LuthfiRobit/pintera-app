import './bootstrap';

import Alpine from 'alpinejs';
import { registerToastStore } from './toast-store';
import { rolesTable } from './roles-table';

window.Alpine = Alpine;

registerToastStore(Alpine);
Alpine.data('rolesTable', rolesTable);

Alpine.start();
