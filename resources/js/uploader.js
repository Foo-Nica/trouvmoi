import Uppy from '@uppy/core';
import Dashboard from '@uppy/dashboard';
import XHRUpload from '@uppy/xhr-upload';
import French from '@uppy/locales/fr_FR';
import '@uppy/core/css/style.min.css';
import '@uppy/dashboard/css/style.min.css';

/**
 * Zone d'envoi des photos d'un événement.
 * - 4 envois en parallèle, chaque photo réessayée automatiquement si la connexion coupe ;
 * - un lot relancé après une coupure ne crée pas de doublons (empreinte côté serveur).
 */
window.trouvmoiUploader = function (target, { endpoint, csrf, maxMb, onComplete }) {
    const uppy = new Uppy({
        locale: French,
        autoProceed: true,
        restrictions: {
            maxFileSize: maxMb * 1024 * 1024,
            allowedFileTypes: ['.jpg', '.jpeg', '.png', '.heic', '.heif', '.webp'],
        },
    });

    uppy.use(Dashboard, {
        target,
        inline: true,
        height: 220,
        width: '100%',
        theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
        proudlyDisplayPoweredByUppy: false,
        showProgressDetails: true,
        hideProgressDetails: false,
        note: `JPG, PNG, HEIC ou WebP · ${maxMb} Mo maximum par photo`,
        locale: {
            strings: {
                dropPasteFiles: 'Glissez vos photos ici ou %{browseFiles}',
                browseFiles: 'parcourez vos fichiers',
            },
        },
    });

    uppy.use(XHRUpload, {
        endpoint,
        fieldName: 'file',
        formData: true,
        limit: 4,
        timeout: 0,
        headers: { 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
        shouldRetry: (xhr) => xhr.status === 0 || xhr.status === 429 || xhr.status >= 500,
        getResponseError: (text) => {
            try {
                return new Error(JSON.parse(text).message);
            } catch {
                return new Error('Envoi impossible');
            }
        },
    });

    uppy.on('complete', (result) => onComplete?.(result));

    return uppy;
};
