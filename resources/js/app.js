import './bootstrap';

import Alpine from 'alpinejs';
import { registerToastStore } from './toast-store';
import { rolesTable } from './roles-table';
import { roleForm } from './role-form';
import { dataDiriForm } from './data-diri-form';
import { otpInput } from './otp-input';
import { pendaftaranTable } from './pendaftaran-table';
import { pendaftaranDetail } from './pendaftaran-detail';
import { nilaiMassal } from './nilai-massal';
import { tagihanTable } from './tagihan-table';
import { pembayaranTable } from './pembayaran-table';

window.Alpine = Alpine;

registerToastStore(Alpine);
Alpine.data('rolesTable', rolesTable);
Alpine.data('roleForm', roleForm);
Alpine.data('dataDiriForm', dataDiriForm);
Alpine.data('otpInput', otpInput);
Alpine.data('pendaftaranTable', pendaftaranTable);
Alpine.data('pendaftaranDetail', pendaftaranDetail);
Alpine.data('nilaiMassal', nilaiMassal);
Alpine.data('tagihanTable', tagihanTable);
Alpine.data('pembayaranTable', pembayaranTable);

Alpine.start();
