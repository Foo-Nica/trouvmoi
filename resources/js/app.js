import Alpine from 'alpinejs';

/** Galerie à défilement infini (page suivante chargée en approchant du bas). */
Alpine.data('gallery', (initial, endpoint) => ({
    photos: initial.photos,
    next: initial.next,
    loading: false,
    init() {
        const observer = new IntersectionObserver((entries) => {
            if (entries.some((e) => e.isIntersecting)) this.more();
        }, { rootMargin: '800px' });
        observer.observe(this.$refs.sentinel);
    },
    async more() {
        if (!this.next || this.loading) return;
        this.loading = true;
        try {
            const res = await fetch(`${endpoint}?page=${this.next}`, { headers: { Accept: 'application/json' } });
            const data = await res.json();
            this.photos.push(...data.photos);
            this.next = data.next;
        } finally {
            this.loading = false;
        }
    },
}));

/**
 * Selfie : réduit la photo dans le téléphone (1280 px) avant l'envoi,
 * pour une recherche rapide même en 3G.
 */
Alpine.data('selfie', () => ({
    busy: false,
    preview: null,
    async pick(event) {
        const file = event.target.files[0];
        if (!file) return;
        this.busy = true;
        try {
            const blob = await resize(file, 1280);
            this.preview = URL.createObjectURL(blob);
            const data = new DataTransfer();
            data.items.add(new File([blob], 'selfie.jpg', { type: 'image/jpeg' }));
            this.$refs.upload.files = data.files;
        } catch {
            // Format non lu par le navigateur (HEIC…) : on envoie l'original.
            const data = new DataTransfer();
            data.items.add(file);
            this.$refs.upload.files = data.files;
        }
        this.$refs.form.submit();
    },
}));

/** Résultats : compteur des photos cochées. */
Alpine.data('results', (count) => ({
    count,
    update() {
        this.count = this.$root.querySelectorAll('input[name="photos[]"]:checked').length;
    },
}));

Alpine.data('share', (url, text) => ({
    copied: false,
    async copy() {
        try {
            await navigator.clipboard.writeText(url);
            this.copied = true;
            setTimeout(() => (this.copied = false), 2000);
        } catch {
            window.prompt(text, url);
        }
    },
    async native() {
        if (navigator.share) {
            try { await navigator.share({ title: text, url }); } catch { /* annulé */ }
        } else {
            this.copy();
        }
    },
}));

async function resize(file, max) {
    const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
    const scale = Math.min(1, max / Math.max(bitmap.width, bitmap.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);
    canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    return new Promise((resolve, reject) =>
        canvas.toBlob((b) => (b ? resolve(b) : reject(new Error('resize'))), 'image/jpeg', 0.88),
    );
}

window.Alpine = Alpine;
Alpine.start();
