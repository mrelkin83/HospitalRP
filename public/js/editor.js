// CKEditor 5 autoalojado, solo en el panel. La barra de herramientas ofrece
// exactamente lo que la lista blanca del SanitizadorHtml conserva: dar un
// botón cuyo resultado el servidor va a borrar es engañar al funcionario.
(function () {
    'use strict';

    var area = document.querySelector('[data-editor="contenido"]');

    if (!area || typeof CKEDITOR === 'undefined') {
        return;
    }

    var C = CKEDITOR;

    C.ClassicEditor.create(area, {
        licenseKey: 'GPL',
        language: 'es',
        plugins: [
            C.Essentials, C.Paragraph, C.Heading,
            C.Bold, C.Italic, C.Underline, C.Strikethrough, C.Subscript, C.Superscript,
            C.Link, C.List, C.BlockQuote,
            C.Image, C.ImageToolbar, C.ImageCaption, C.ImageStyle, C.ImageInsert, C.ImageInsertViaUrl,
        ],
        toolbar: [
            'heading', '|',
            'bold', 'italic', 'underline', '|',
            'link', 'bulletedList', 'numberedList', 'blockQuote', '|',
            'insertImageViaUrl', '|',
            'undo', 'redo',
        ],
        heading: {
            options: [
                { model: 'paragraph', title: 'Párrafo', class: 'ck-heading_paragraph' },
                { model: 'heading2', view: 'h2', title: 'Título de sección', class: 'ck-heading_heading2' },
                { model: 'heading3', view: 'h3', title: 'Subtítulo', class: 'ck-heading_heading3' },
                { model: 'heading4', view: 'h4', title: 'Título menor', class: 'ck-heading_heading4' },
            ],
        },
        image: {
            toolbar: ['imageTextAlternative', 'toggleImageCaption'],
            insert: { type: 'block' },
        },
        link: {
            addTargetToExternalLinks: false,
        },
    }).catch(function (error) {
        // Si el editor no arranca, el textarea sigue funcionando: el
        // funcionario nunca queda bloqueado.
        console.error(error);
    });
})();
