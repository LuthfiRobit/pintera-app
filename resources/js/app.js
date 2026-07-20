import './bootstrap';

import Alpine from 'alpinejs';
import anchor from '@alpinejs/anchor';
import { registerToastStore } from './toast-store';
import { registerConfirmDialogStore } from './confirm-dialog-store';
import { registerKelengkapanStore } from './kelengkapan-store';
import { rolesTable } from './roles-table';
import { roleForm } from './role-form';
import { dataDiriForm } from './data-diri-form';
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

window.Alpine = Alpine;

Alpine.plugin(anchor);

registerToastStore(Alpine);
registerConfirmDialogStore(Alpine);
registerKelengkapanStore(Alpine);
Alpine.data('rolesTable', rolesTable);
Alpine.data('roleForm', roleForm);
Alpine.data('dataDiriForm', dataDiriForm);
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

Alpine.start();
