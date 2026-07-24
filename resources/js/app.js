import './bootstrap';

import Alpine from 'alpinejs';
import anchor from '@alpinejs/anchor';
import flatpickr from 'flatpickr';
import { Indonesian } from 'flatpickr/dist/l10n/id.js';
import { registerToastStore } from './toast-store';
import { registerConfirmDialogStore } from './confirm-dialog-store';
import { registerKelengkapanStore } from './kelengkapan-store';
import { registerFilePreviewStore } from './file-preview';
import { rolesTable } from './roles-table';
import { roleForm } from './role-form';
import { dataDiriForm } from './data-diri-form';
import { formulirTambahanForm } from './formulir-tambahan-form';
import { otpInput } from './otp-input';
import { pendaftaranTable } from './pendaftaran-table';
import { pendaftaranDetail } from './pendaftaran-detail';
import { nilaiMassal } from './nilai-massal';
import { tagihanTable } from './tagihan-table';
import { pembayaranTable } from './pembayaran-table';
import { trenPendaftaranChart, donutTagihanChart, perLembagaBarChart } from './dashboard-charts';
import { dokumenSyaratList } from './dokumen-syarat-list';
import { formulirFieldList } from './formulir-field-list';
import { seleksiList } from './seleksi-list';
import { jenisTesTable } from './jenis-tes-table';
import { jenisTagihanTable } from './jenis-tagihan-table';
import { passwordStrength } from './password-strength';

window.Alpine = Alpine;
window.flatpickr = flatpickr;
flatpickr.localize(Indonesian);

Alpine.plugin(anchor);

registerToastStore(Alpine);
registerConfirmDialogStore(Alpine);
registerKelengkapanStore(Alpine);
registerFilePreviewStore(Alpine);
Alpine.data('rolesTable', rolesTable);
Alpine.data('roleForm', roleForm);
Alpine.data('dataDiriForm', dataDiriForm);
Alpine.data('formulirTambahanForm', formulirTambahanForm);
Alpine.data('otpInput', otpInput);
Alpine.data('pendaftaranTable', pendaftaranTable);
Alpine.data('pendaftaranDetail', pendaftaranDetail);
Alpine.data('nilaiMassal', nilaiMassal);
Alpine.data('tagihanTable', tagihanTable);
Alpine.data('pembayaranTable', pembayaranTable);
Alpine.data('trenPendaftaranChart', trenPendaftaranChart);
Alpine.data('donutTagihanChart', donutTagihanChart);
Alpine.data('perLembagaBarChart', perLembagaBarChart);
Alpine.data('dokumenSyaratList', dokumenSyaratList);
Alpine.data('formulirFieldList', formulirFieldList);
Alpine.data('seleksiList', seleksiList);
Alpine.data('jenisTesTable', jenisTesTable);
Alpine.data('jenisTagihanTable', jenisTagihanTable);
Alpine.data('passwordStrength', passwordStrength);

Alpine.start();
