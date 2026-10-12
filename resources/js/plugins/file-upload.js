/**
 * FilePond (pqina.nl/filepond) for the Excel upload fields: input[type=file][data-file-upload].
 * A drop area with the file's name and size instead of the browser's bare "Choose File" button.
 *
 * The file still travels with its form (storeAsFile), so the upload goes through the same preview and
 * confirmation as before; FilePond only checks type and size up front. The server checks both again.
 */
import * as FilePond from 'filepond';
import FilePondPluginFileValidateSize from 'filepond-plugin-file-validate-size';
import FilePondPluginFileValidateType from 'filepond-plugin-file-validate-type';
import 'filepond/dist/filepond.min.css';

FilePond.registerPlugin(FilePondPluginFileValidateType, FilePondPluginFileValidateSize);

// File types are compared as extensions, the same form as the input's accept attribute (which FilePond reads
// and which takes precedence over options passed here).
const EXTENSIONS = ['.xlsx', '.xls', '.csv'];

export function initFileUploads(root = document) {
    root.querySelectorAll('input[type="file"][data-file-upload]').forEach((input) => {
        FilePond.create(input, {
            storeAsFile: true,
            allowMultiple: false,
            required: input.required,
            credits: false,
            acceptedFileTypes: EXTENSIONS,
            // Browsers disagree on the MIME type of a .csv (and some report none at all), so go by the extension.
            fileValidateTypeDetectType: (file) => Promise.resolve(`.${file.name.split('.').pop().toLowerCase()}`),
            maxFileSize: input.dataset.maxSize ?? '2MB',
            labelIdle: 'Seret file Excel ke sini atau <span class="filepond--label-action">pilih file</span>',
            labelFileTypeNotAllowed: 'Jenis file tidak didukung',
            fileValidateTypeLabelExpectedTypes: 'Gunakan .xlsx, .xls, atau .csv',
            labelMaxFileSizeExceeded: 'File terlalu besar',
            labelMaxFileSize: 'Ukuran maksimal {filesize}',
            labelFileLoading: 'Memuat',
            labelFileLoadError: 'File gagal dibaca',
            labelFileWaitingForSize: 'Menghitung ukuran',
            labelInvalidField: 'File tidak valid',
            labelButtonRemoveItem: 'Hapus file',
            labelTapToCancel: 'ketuk untuk batal',
        });
    });
}
