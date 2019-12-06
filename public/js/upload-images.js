//Create well design file upload form file
$(document).on('change', ':file', function () {
    var input = $(this),
            numFiles = input.get(0).files ? input.get(0).files.length : 1,
            label = input.val().replace(/\\/g, '/').replace(/.*\//, '');
    input.trigger('fileselect', [numFiles, label]);

    $('input[name=fileUploadFileName]').val(label);
});

$(document).ready(function () {

//Open model afbeeldingen
    $('#showImages').on('shown.bs.modal', function () {
    });

//When modal edit afbeelding close open afbeeldingen modal
    $('#editImage').on('hidden.bs.modal', function () {
        $(function () {
            $('#showImages').modal('toggle');
        });
        $('input[name=editNameImage]').val('');
        $('input[name=editAlt]').val('');
        $('input[name=editDdescriptionImage]').val('');
    })


//Ajax function to edit Image
    $("span.editImage").on("click", function () {

        $(function () {
            $('#editImage').modal('toggle');
        });
        var imageId = $(this).data('imageid');
        $.ajax({
            type: 'POST',
            data: {
                imageId: imageId,
            },
            url: "/ajaximage/getImage",
            async: true,
            success: function (data) {
                if (data.succes === true) {
                    $('input[name=editNameImage]').val(data.imageDetails.imageName);
                    $('input[name=editAlt]').val(data.imageDetails.imageAlt);
                    $('input[name=editDdescriptionImage]').val(data.imageDetails.imageDescription);
                    $('input[name=imageId]').val(data.imageId);
                } else {
                    alert(data.errorMessage);
                }
            }
        });

    });

//Ajax function to save edited image
    $("button#saveImageDetails").on("click", function () {
        var imageId = $('input[name=imageId]').val();
        var nameImage = $('input[name=editNameImage]').val();
        var alt = $('input[name=editAlt]').val();
        var descriptionImage = $('input[name=editDdescriptionImage]').val();
        $.ajax({
            type: 'POST',
            data: {
                imageId: imageId,
                nameImage: nameImage,
                alt: alt,
                descriptionImage: descriptionImage
            },
            url: "/ajaximage/saveImage",
            async: true,
            success: function (data) {
                if (data.succes === true) {
                    $(function () {
                        $('#editImage').modal('toggle');
                    });

                } else {
                    alert(data.errorMessage);
                }
            }
        });
    });


//Ajax function to delete image 
    $("span.deleteImageObject").on("click", function () {
        var imageId = $(this).data('imageid');
        $.ajax({
            type: 'POST',
            data: {
                imageId: imageId,
            },
            url: "/ajaximage/delete",
            async: true,
            success: function (data) {
                if (data.succes === true) {
                    $('#image' + data.imageId).parent('div').fadeOut(300, function () {
                        $('#image' + data.imageId).parent('div').remove();
                    })
                    location.reload();
                } else {
                    alert(data.errorMessage);
                }
            }
        });
    });

//Ajax function to recrop image
    $("span.recropImage").on("click", function () {
        var imageId = $(this).data('imageid');
        var route = $(this).data('route');
        var action = $(this).data('action');
        var id = $(this).data('id');
        $.ajax({
            type: 'POST',
            data: {
                imageId: imageId,
                route: route,
                action: action,
                id: id
            },
            url: "/ajaximage/reCrop",
            async: true,
            success: function (data) {
                if (data.succes === true) {
                    window.location.replace('/image/crop');
                } else {
                    alert(data.errorMessage);
                }
            }
        });
    });

    //Ajax function to rotate image
    $("span.rotateImage").on("click", function () {
        var imageId = $(this).data('imageid');
        var route = $(this).data('route');
        var action = $(this).data('action');
        var id = $(this).data('id');
        $.ajax({
            type: 'POST',
            data: {
                imageId: imageId,
                route: route,
                action: action,
                id: id
            },
            url: "/ajaximage/rotate",
            async: true,
            success: function (data) {
                if (data.succes === true) {
                    window.location.replace('/image/rotate');
                } else {
                    alert(data.errorMessage);
                }
            }
        });
    });

    /*
     * Set all checkboxes to true
     * 
     * @return void
     */
    $("input[name='checkAll']").on("change", function () {
        var atLeastOneIsChecked = $("input[name='checkAll']:checked").length > 0;
        if (atLeastOneIsChecked) {
            $("input[name='url']").prop('checked', true);
        } else {
            $("input[name='url']").prop('checked', false);
        }

    });

    /*
     * Ajax function to check images with server and db
     */
    $("span.checkImages").on("click", function () {
        var linksArr = [];
        $("input[name='url']:checked").each(function (index) {
            var url = $(this).val();
            var id = url.substring(0, url.lastIndexOf("|") + 1);
            id = id.replace("|", "");
            var url = url.substring(url.lastIndexOf("|") + 1, url.length);
            var folder = url.substring(0, url.lastIndexOf("/") + 1);
            var name = url.substring(url.lastIndexOf("/") + 1, url.length);
            //Create object
            var linkArr = [];
            linkArr['id'] = id;
            linkArr['name'] = name;
            linkArr['folder'] = folder;
            //Push object into array with index
            linksArr[index] = linkArr;
        });

        processLinksSvArray(linksArr);
    });

    /*
     * Proccess the given array
     * 
     * @return void
     */
    function processLinksSvArray(linksArr) {
        if (linksArr.length > 0) {
            var id = linksArr[0]['id'];
            var name = linksArr[0]['name'];
            var folder = linksArr[0]['folder'];

            var linksArr = $.grep(linksArr, function (e) {
                return e.id != id;
            });

            processLinksSvArrayAjax(linksArr, id, name, folder);
        }
    }

    function processLinksSvArrayAjax(linksArr, id, name, folder) {
        $.ajax({
            type: 'POST',
            data: {
                id: id,
                name: name,
                folder: folder
            },
            url: "/ajaximage/checkServerImage",
            async: true,
            success: function (data) {
                if (data.succes === true) {
                    $('tr#img' + data.id + ' > td.result').html('<i class="fas fa-chevron-circle-down text-success"></i>');
                } else {
                    $('tr#img' + data.id + ' > td.result').html('<span class="deleteImage" data-id="' + id + '" data-url="' + folder + name + '"><i class="fas fa-times-circle text-danger"></i></span>');
                }
                processLinksSvArray(linksArr);
            },
            error: function (XMLHttpRequest, textStatus, errorThrown) {
                alert(errorThrown);
            }
        });
    }

    /*
     * Ajax function to check images with db and servers
     * 
     * @return void
     */
    $("span.checkDatabaseImages").on("click", function () {
        var linksArr = [];
        $("input[name='url']:checked").each(function (index) {
            var url = $(this).val();
            var id = url.substring(0, url.lastIndexOf("|") + 1);
            id = id.replace("|", "");
            var url = url.substring(url.lastIndexOf("|") + 1, url.length);
            var folder = url.substring(0, url.lastIndexOf("/") + 1);
            var name = url.substring(url.lastIndexOf("/") + 1, url.length);
            //Create object
            var linkArr = [];
            linkArr['id'] = id;
            linkArr['name'] = name;
            linkArr['folder'] = folder;
            //Push object into array with index
            linksArr[index] = linkArr;
        });
        console.log('ja');
        processLinksDbArray(linksArr);
    });

    /*
     * Proccess the given array
     * 
     * @return void
     */
    function processLinksDbArray(linksArr) {
        if (linksArr.length > 0) {
            var id = linksArr[0]['id'];
            var name = linksArr[0]['name'];
            var folder = linksArr[0]['folder'];

            var linksArr = $.grep(linksArr, function (e) {
                return e.id != id;
            });

            processLinksDbArrayAjax(linksArr, id, name, folder);
        }
    }

    /*
     * Execute ajax call to check if image in db is on the server
     * 
     * @return void
     */
    function processLinksDbArrayAjax(linksArr, id, name, folder) {
        $.ajax({
            type: 'POST',
            data: {
                id: id,
                name: name,
                folder: folder
            },
            url: "/ajaximage/checkDatabseImage",
            async: true,
            success: function (data) {
                if (data.succes === true) {
                    $('tr#img' + data.id + ' > td.result').html('<i class="fas fa-chevron-circle-down text-success"></i>');
                } else {
                    $('tr#img' + data.id + ' > td.result').html('<span class="deleteImageRow" data-id="' + id + '" data-url="' + folder + name + '"><i class="fas fa-times-circle text-danger"></i></span>');
                }
                processLinksDbArray(linksArr);
            },
            error: function (XMLHttpRequest, textStatus, errorThrown) {
                alert(errorThrown);
            }
        });
    }

    /*
     * Ajax function to delete image from server
     * 
     * @return void
     */
    $(document).on("click", "span.deleteImage", function () {
        var id = $(this).data('id');
        var url = $(this).data('url');
        $.ajax({
            type: 'POST',
            data: {
                id: id,
                url: url
            },
            url: "/ajaximage/deleteImageFromServer",
            async: true,
            success: function (data) {
                if (data.succes === true) {
                    $('tr#img' + id).remove();
                } else {
                    alert('Image not removed!');
                }
            }
        });

    });
    /*
     * Ajax function to delete image from database
     * 
     * @return void
     */
    $(document).on("click", "span.deleteImageRow", function () {
        var id = $(this).data('id');
        var url = $(this).data('url');
        $.ajax({
            type: 'POST',
            data: {
                id: id,
                url: url
            },
            url: "/ajaximage/deleteImageFromDatabase",
            async: true,
            success: function (data) {
                if (data.succes === true) {
                    $('tr#img' + id).remove();
                } else {
                    alert('Image not removed!');
                }
            }
        });

    });


});