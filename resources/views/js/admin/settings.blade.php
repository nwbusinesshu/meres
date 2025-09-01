<script>
    // A teljes JavaScript kódod ide másolva a lang objektum használatával
    document.addEventListener('DOMContentLoaded', function() {
        const strictEl = document.getElementById('toggle-strict');
        const aiEl = document.getElementById('toggle-ai');

        function postToggle(key, value) {
            return fetch("{{ route('admin.settings.toggle') }}", {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ key, value })
            }).then(r => r.json());
        }

        function warnConfirm(text) {
            return Swal.fire({
                icon: 'warning',
                title: lang.confirm, // Itt használod a lefordított szöveget
                text: text,
                showCancelButton: true,
                confirmButtonText: lang.yes, // És itt
                cancelButtonText: lang.no // És itt
            }).then(res => res.isConfirmed);
        }

        function okToast(msg) {
            return Swal.fire({
                icon: 'success',
                title: msg,
                timer: 1200,
                showConfirmButton: false
            });
        }

        strictEl?.addEventListener('change', async (e) => {
            const nextVal = e.target.checked;
            const ok = await warnConfirm(lang.warn_strict_on); // Használd a `lang` objektumot
            if (!ok) { e.target.checked = !nextVal; return; }

            try {
                await postToggle('strict_anonymous_mode', nextVal ? 1 : 0);
                if (nextVal) {
                    aiEl.checked = false;
                    aiEl.setAttribute('disabled','disabled');
                } else {
                    aiEl.removeAttribute('disabled');
                }
                okToast(lang.saved);
            } catch (err) {
                e.target.checked = !nextVal;
                Swal.fire({ icon:'error', title:lang.error, text:String(err) });
            }
        });

        aiEl?.addEventListener('change', async (e) => {
            const nextVal = e.target.checked;
            const ok = await warnConfirm(
                nextVal ? lang.warn_ai_on : lang.warn_ai_off // Használd a `lang` objektumot
            );
            if (!ok) { e.target.checked = !nextVal; return; }

            try {
                await postToggle('ai_telemetry_enabled', nextVal ? 1 : 0);
                okToast(lang.saved);
            } catch (err) {
                e.target.checked = !nextVal;
                Swal.fire({ icon:'error', title:lang.error, text:String(err) });
            }
        });
    });
</script>