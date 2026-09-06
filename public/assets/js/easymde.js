$(function () {
    "use strict";

    /*easymde editor*/
    if ($("#easyMdeExample").length) {
        var easymde = new EasyMDE({
            element: $("#easyMdeExample")[0],
            uploadImage: true,
            imageUploadEndpoint: "/admin/news/upload-image",
            imagePathAbsolute: true,
        });
    }

    if ($("#easyMdeExample2").length) {
        var easymde = new EasyMDE({
            element: $("#easyMdeExample2")[0],
            uploadImage: true,
            imageUploadEndpoint: "/admin/news/upload-image",
            imagePathAbsolute: true,
        });
    }
});
