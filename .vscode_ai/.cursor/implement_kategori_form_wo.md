Here is the explanation you can pass directly to the backend AI agent (`Opus 4.8`):

---

### Backend Requirements: Update Category Fields on Work Order Submission

Please update the Work Order progress submission endpoint to support updating the category tables (`wo_meter`, `wo_jaringan`, `wo_infrastruktur`) with the final fields when a progress report is completed (type `SELESAI`).

#### 1. Target Controller
- **File**: `app/Http/Controllers/ProgressWorkorderController.php`
- **Method**: `submit(Request $request)`

#### 2. Models to Import (if not already imported)
- `App\Models\WoMeter`
- `App\Models\WoJaringan`
- `App\Models\WoInfrastruktur`

#### 3. Validation Logic
In the `submit` method, before calling `$request->validate(...)`, check the work order category and dynamically append validation rules if `tipe_progress_kode` or `tipe_progress` is equal to `'SELESAI'`:

```php
$rules = [
    'workorder_id' => 'required|exists:workorder,id',
    'tipe_progress_kode' => 'required_without:tipe_progress|nullable|in:PROGRESS,SELESAI',
    'tipe_progress' => 'required_without:tipe_progress_kode|nullable|in:PROGRESS,SELESAI',
    'hasil_pengerjaan' => 'required|string|max:255',
    'latitude'  => 'required|numeric',
    'longitude' => 'required|numeric',
    'accuracy'  => 'nullable|numeric',
    'foto' => 'nullable|array',
    'foto.*' => 'image|mimes:jpeg,png,jpg|max:2048',
];

$tipeProgressKode = $request->input('tipe_progress_kode') ?? $request->input('tipe_progress');
if ($tipeProgressKode === 'SELESAI') {
    $workorder = Workorder::find($request->input('workorder_id'));
    if ($workorder) {
        $kategori = optional($workorder->jenisWorkorder)->kategori_form;
        if ($kategori === 'meter') {
            $rules['kondisi_meter_akhir'] = 'required|string';
            $rules['hasil_kalibrasi'] = 'required|string';
        } elseif ($kategori === 'jaringan') {
            $rules['tindakan_perbaikan'] = 'required|string';
            $rules['hasil_inspeksi'] = 'required|string';
        } elseif ($kategori === 'infrastruktur') {
            $rules['kondisi_awal'] = 'required|string';
            $rules['kondisi_akhir'] = 'required|string';
            $rules['jadwal_pemeliharaan'] = 'required|date';
            $rules['tindakan'] = 'required|string';
        }
    }
}

$validated = $request->validate($rules);
```

#### 4. Save/Update Logic
Inside the `DB::beginTransaction()` block in `submit()`, update the corresponding category table if the progress is `'SELESAI'`:

```php
if ($tipeProgressKode === 'SELESAI') {
    $workorder->update([
        'status_id' => $this->statusId('PENGECEKAN'),
        'tanggal_selesai' => now(),
    ]);

    // Update category fields
    $kategori = optional($workorder->jenisWorkorder)->kategori_form;
    if ($kategori === 'meter') {
        $woMeter = WoMeter::where('workorder_id', $workorder->id)->first();
        if ($woMeter) {
            $woMeter->update([
                'kondisi_meter_akhir' => $validated['kondisi_meter_akhir'],
                'hasil_kalibrasi' => $validated['hasil_kalibrasi'],
            ]);
        }
    } elseif ($kategori === 'jaringan') {
        $woJaringan = WoJaringan::where('workorder_id', $workorder->id)->first();
        if ($woJaringan) {
            $woJaringan->update([
                'tindakan_perbaikan' => $validated['tindakan_perbaikan'],
                'hasil_inspeksi' => $validated['hasil_inspeksi'],
            ]);
        }
    } elseif ($kategori === 'infrastruktur') {
        $woInfrastruktur = WoInfrastruktur::where('workorder_id', $workorder->id)->first();
        if ($woInfrastruktur) {
            $woInfrastruktur->update([
                'kondisi_awal' => $validated['kondisi_awal'],
                'kondisi_akhir' => $validated['kondisi_akhir'],
                'jadwal_pemeliharaan' => $validated['jadwal_pemeliharaan'],
                'tindakan' => $validated['tindakan'],
            ]);
        }
    }
}
```

---

Let me know if you approve this plan for the Frontend part, and I will proceed with the mobile implementation!