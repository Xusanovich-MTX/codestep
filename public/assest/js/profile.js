(function () {
    'use strict';
    const page = document.getElementById('profilePage');
    if (!page) return;
    const byId = id => document.getElementById(id);
    let textBusy = false;
    let imageBusy = false;
    let passwordBusy = false;
    let dirty = false;
    function status(id, text, error = false) {
        byId(id).textContent = text;
        byId(id).dataset.error = String(error);
    }
    async function send(url, body) {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 20000);
        try {
            const response = await fetch(url, {method:'POST', body, credentials:'same-origin', cache:'no-store', headers:{'X-CSRF-Token':page.dataset.csrfToken}, signal:controller.signal});
            const result = await response.json();
            if (!response.ok || result.error) throw new Error(result.message || result.error || 'Amal bajarilmadi.');
            return result;
        } catch (error) {
            if (error.name === 'AbortError') throw new Error('Server vaqtida javob bermadi. Sahifani tekshirib, qayta urinib ko‘ring.');
            if (error instanceof TypeError || error instanceof SyntaxError) throw new Error('Serverga ulanib bo‘lmadi. Qayta urinib ko‘ring.');
            throw error;
        } finally { clearTimeout(timer); }
    }
    function countBio() { byId('profileBioCount').textContent = `${Array.from(byId('profileBio').value).length} / 280`; }
    byId('profileForm').addEventListener('input', () => { dirty = true; countBio(); });
    byId('profileForm').addEventListener('submit', async event => {
        event.preventDefault();
        if (textBusy || passwordBusy) return;
        const snapshot = {display_name:byId('profileName').value, bio:byId('profileBio').value};
        const nameLength = Array.from(snapshot.display_name.trim()).length;
        if (nameLength < 1 || nameLength > 80 || Array.from(snapshot.bio.trim()).length > 280) {
            status('profileSaveStatus', 'Ism 1–80 ta, o‘zingiz haqingizdagi matn esa 280 tagacha belgidan iborat bo‘lsin.', true);
            return;
        }
        textBusy = true;
        byId('profileSave').disabled = true;
        status('profileSaveStatus', 'Profil saqlanmoqda…');
        try {
            const result = await send('api/profile.php', new URLSearchParams({action:'save', ...snapshot}));
            document.querySelectorAll('[data-profile-name]').forEach(node => { node.textContent = result.profile.display_name; });
            byId('profileBioPreview').textContent = result.profile.bio;
            byId('profileBioPreview').hidden = result.profile.bio === '';
            dirty = snapshot.display_name !== byId('profileName').value || snapshot.bio !== byId('profileBio').value;
            status('profileSaveStatus', dirty ? 'Yuborilgan ma’lumot saqlandi. Keyingi o‘zgarishlarni ham saqlang.' : 'Profil ma’lumotlari saqlandi.');
        } catch (error) { status('profileSaveStatus', error.message, true); }
        finally { textBusy = false; byId('profileSave').disabled = false; }
    });
    function updateAvatar(url) {
        document.querySelectorAll('[data-profile-avatar]').forEach(current => {
            const replacement = document.createElement(url ? 'img' : 'span');
            replacement.id = current.id;
            replacement.className = current.className.replace(/\b(?:profile-initial|avatar-placeholder)\b/g, '').trim();
            replacement.dataset.profileAvatar = '';
            if (url) { replacement.src = url; replacement.alt = 'Profil rasmi'; }
            else { replacement.classList.add('profile-initial'); replacement.textContent = page.dataset.initial; replacement.setAttribute('aria-label','Profil rasmi'); }
            current.replaceWith(replacement);
        });
        byId('profileAvatarRemove').hidden = !url;
    }
    function imageControls(busy) {
        imageBusy = busy;
        byId('profileAvatarInput').disabled = busy;
        byId('profileAvatarRemove').disabled = busy;
    }
    byId('profileAvatarInput').addEventListener('change', async event => {
        const file = event.target.files[0];
        if (!file || imageBusy || passwordBusy) return;
        if (!['image/jpeg','image/png','image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) {
            status('profileAvatarStatus', 'JPG, PNG yoki WebP rasm tanlang (2 MB gacha).', true);
            event.target.value = '';
            return;
        }
        imageControls(true);
        status('profileAvatarStatus', 'Rasm yuklanmoqda…');
        try {
            const body = new FormData();
            body.append('profile_image',file);
            const result = await send('api/upload-profile.php', body);
            updateAvatar(result.image);
            status('profileAvatarStatus', 'Profil rasmi saqlandi.');
        } catch (error) { status('profileAvatarStatus', error.message, true); }
        finally { imageControls(false); event.target.value = ''; }
    });
    byId('profileAvatarRemove').addEventListener('click', async () => {
        if (imageBusy || passwordBusy) return;
        imageControls(true);
        status('profileAvatarStatus', 'Rasm olib tashlanmoqda…');
        try {
            await send('api/profile.php', new URLSearchParams({action:'remove_avatar'}));
            updateAvatar(null);
            status('profileAvatarStatus', 'Profil rasmi olib tashlandi.');
        } catch (error) { status('profileAvatarStatus', error.message, true); }
        finally { imageControls(false); }
    });
    byId('profilePasswordForm').addEventListener('submit', async event => {
        event.preventDefault();
        if (passwordBusy) return;
        if (textBusy || imageBusy) { status('profilePasswordStatus', 'Profilni saqlash tugashini kuting.'); return; }
        const body = new URLSearchParams(new FormData(event.currentTarget));
        if (body.get('new_password') !== body.get('password_confirm')) {
            status('profilePasswordStatus', 'Yangi parollar bir xil emas.', true);
            return;
        }
        passwordBusy = true;
        byId('profilePasswordSave').disabled = true;
        byId('profileSave').disabled = true;
        byId('profileAvatarInput').disabled = true;
        byId('profileAvatarRemove').disabled = true;
        status('profilePasswordStatus', 'Parol yangilanmoqda…');
        try {
            const result = await send('api/profile-password.php', body);
            page.dataset.csrfToken = result.csrf_token;
            document.querySelectorAll('input[name="csrf_token"]').forEach(input => { input.value = result.csrf_token; });
            byId('profilePasswordForm').reset();
            status('profilePasswordStatus', result.message);
        } catch (error) { status('profilePasswordStatus', error.message, true); }
        finally {
            passwordBusy = false;
            byId('profilePasswordSave').disabled = false;
            byId('profileSave').disabled = false;
            byId('profileAvatarInput').disabled = false;
            byId('profileAvatarRemove').disabled = false;
        }
    });
    const deleteForm = byId('profileDeleteForm');
    if (deleteForm) {
        deleteForm.addEventListener('submit', async event => {
            event.preventDefault();
            if (textBusy || imageBusy || passwordBusy) return;
            const pwd = byId('profileDeletePassword').value;
            if (!pwd) {
                status('profileDeleteStatus', 'Tasdiqlash uchun joriy parolingizni kiriting.', true);
                return;
            }
            if (!window.confirm('Haqiqatan ham hisobingizni va barcha saqlangan ma’lumotlaringizni butunlay o‘chirmoqchimisiz? Bu amalni ortga qaytarib bo‘lmaydi!')) {
                return;
            }
            passwordBusy = true;
            byId('profileDeleteBtn').disabled = true;
            status('profileDeleteStatus', 'Hisob o‘chirilmoqda…');
            try {
                const result = await send('api/profile.php', new URLSearchParams({
                    action: 'delete_account',
                    current_password: pwd
                }));
                dirty = false;
                status('profileDeleteStatus', result.message || 'Hisob o‘chirildi.');
                window.location.href = '?page=home';
            } catch (error) {
                status('profileDeleteStatus', error.message, true);
                passwordBusy = false;
                byId('profileDeleteBtn').disabled = false;
            }
        });
    }
    window.addEventListener('beforeunload', event => {
        if (dirty || textBusy || imageBusy || passwordBusy) { event.preventDefault(); event.returnValue = ''; }
    });
    countBio();
}());
