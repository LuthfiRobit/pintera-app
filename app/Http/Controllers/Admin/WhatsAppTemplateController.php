<?php

namespace App\Http\Controllers\Admin;

use App\Models\WhatsAppTemplate;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\View\View;

class WhatsAppTemplateController extends BaseController
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('whatsapp-template.edit');

        return view('admin.whatsapp-template.index', [
            'templateList' => WhatsAppTemplate::orderBy('kode')->get(),
        ]);
    }

    public function update(Request $request, WhatsAppTemplate $whatsappTemplate): RedirectResponse
    {
        $this->authorize('whatsapp-template.edit');

        $data = $request->validate([
            'isi_template' => ['required', 'string'],
            'deskripsi' => ['nullable', 'string'],
        ]);

        $whatsappTemplate->update($data);

        return back()->with('status', 'Template WhatsApp berhasil diperbarui.');
    }
}
