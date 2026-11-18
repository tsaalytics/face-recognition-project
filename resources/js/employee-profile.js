export async function saveProfileBeforeEnrollment(enrollment, profile, browser) {
    const status = browser.document.querySelector('[data-profile-status]');
    const response = await browser.fetch(profile.action, {
        method: 'POST', credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': profile.querySelector('[name="_token"]').value },
        body: new browser.FormData(profile),
    });
    let data = null;
    try { data = await response.json(); } catch { /* Non-JSON proxy response. */ }
    if (!response.ok) {
        throw new Error(data?.errors ? Object.values(data.errors).flat().join(' ') : 'Profil belum dapat disimpan. Periksa data atau sesi login; enrollment belum diproses.');
    }
    if (!data?.update_url || !data?.edit_url || !data?.enrollment_url || !Number.isInteger(data.enrollment_version)) {
        throw new Error('Respons penyimpanan profil tidak lengkap. Periksa direktori sebelum mencoba kembali.');
    }
    profile.action = data.update_url;
    profile.querySelector('[name="_method"]').value = 'PUT';
    profile.querySelector('#business_id').value = data.business_id || 'Belum diterbitkan';
    enrollment.action = data.enrollment_url;
    // A stale edit page must still conflict instead of silently replacing newer enrollment.
    if (enrollment.dataset.profilePersisted === '0') enrollment.dataset.version = String(data.enrollment_version);
    enrollment.dataset.profilePersisted = '1';
    browser.history?.replaceState(null, '', data.edit_url);
    if (status) status.textContent = 'Profil karyawan berhasil disimpan. Jika enrollment gagal, profil tetap tersimpan; Anda dapat mencoba ulang atau kembali ke direktori.';
}
