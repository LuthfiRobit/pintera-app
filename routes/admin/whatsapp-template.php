<?php

use App\Http\Controllers\Admin\WhatsAppTemplateController;
use Illuminate\Support\Facades\Route;

Route::get('whatsapp-template', [WhatsAppTemplateController::class, 'index'])->name('whatsapp-template.index');
Route::put('whatsapp-template/{whatsappTemplate}', [WhatsAppTemplateController::class, 'update'])->name('whatsapp-template.update');
