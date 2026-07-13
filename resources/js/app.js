import './bootstrap';

import Alpine from 'alpinejs';
import { registerToastStore } from './toast-store';
import { rolesTable } from './roles-table';
import { roleForm } from './role-form';

window.Alpine = Alpine;

registerToastStore(Alpine);
Alpine.data('rolesTable', rolesTable);
Alpine.data('roleForm', roleForm);

Alpine.start();
