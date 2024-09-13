$(document).ready(function () {

    /**
     * Represents a DataTable instance initialized on the HTML element with ID
     * 'image-result-body'. The DataTable is configured with the following options:
     * - responsive: true (makes the table layout responsive)
     * - searching: false (disables the search functionality)
     * - info: false (hides the table information display)
     * - paging: false (disables pagination)
     * - orderFixed: [0, 'asc'] (fixes ordering by the first column in ascending order)
     * - ordering: false (disables column-based ordering)
     */
    let tableImages = $('#image-result-body').DataTable( {
        responsive: true,
        searching: false,
        info: false,
        paging: false,
        orderFixed: [0, 'asc'],
        ordering: false
    } );

    /**
     * When opening edit modal clear all values
     */
    $('#editImageModal').on('hidden.bs.modal', function () {
        $(function () {
            $('#imageModal').modal('toggle');
        });
        $('input[name=editNameImage]').val('');
        $('input[name=editAlt]').val('');
        $('input[name=editDdescriptionImage]').val('');
    })


    /**
     * Ajax call to edit image
     */
    $("span.editImage").on("click", function () {
        $(function () {
            $('#imageModal').modal('toggle');
            $('#editImageModal').modal('toggle');
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

    /**
     * Ajax function to save edited image
     */
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
                        $('#editImageModal').modal('toggle');
                    });
                } else {
                    alert(data.errorMessage);
                }
            }
        });
    });


    /**
     * Ajax function to delete image
     */
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
                } else {
                    alert(data.errorMessage);
                }
            }
        });
    });

    /**
     * Ajax function to recrop image
     */
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

    /**
     * Ajax function to rotate image
     */
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

    /**
     * Ajax function to check images with server and db
     */
    $("span.checkImages").on("click", function () {
        var linksArr = [];
        $("input[name='url']:checked").each(function (index) {
            var url = $(this).val();
            var id = url.substring(0, url.lastIndexOf("|") + 1);
            id = id.replace("|", "");
            url = url.substring(url.lastIndexOf("|") + 1, url.length);
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
        $('button#break').removeAttr('disabled');
        $('button#break').removeClass('disabled');
        processLinksSvArray(linksArr);
    });

    /*
     * Process the given array
     * @return void
     */
    function processLinksSvArray(linksArr) {
        if (linksArr.length > 0 && breaking === false) {
            var id = linksArr[0]['id'];
            var name = linksArr[0]['name'];
            var folder = linksArr[0]['folder'];

            var linksArr = $.grep(linksArr, function (e) {
                return e.id != id;
            });

            processLinksSvArrayAjax(linksArr, id, name, folder);
        } else {
            $('button#break').attr('disabled', 'disabled');
            $('button#break').addClass('disabled');
            breaking = false;
        }
    }

    /**
     * Sends an AJAX POST request to verify the presence of an image on the server and updates the DOM based on the response.
     * Continues processing the array of links provided after the AJAX call is completed.
     *
     * @param {Array} linksArr - The array of links to be processed.
     * @param {number} id - The unique identifier for the image.
     * @param {string} name - The name of the image file.
     * @param {string} folder - The folder path where the image is stored.
     * @return {void} This function does not return a value.
     */
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

    /**
     * Toggle check database images modal
     */
    $("button#checkAllDatabaseImages").on("click", function () {
        $('div#image-results-table').hide();
        $('button.deleteSelectedImages').prop("disabled", true)
        $('tbody#imageResults').empty();
        $('div#progressBar > div.progress-bar').css('width', 0);
    });

    /**
     * Check al images in the database (compare with file on server)
     */
    $("button#startImageScan").on("click", function () {
        // Disable button to prevent multiple clicking
        $(this).prop('disabled', true);
        // Hide the result table, show it on the end
        $('div#image-results-table').hide();
        // Un disable the cancel button
        $('button#cancelImageScan').prop('disabled', false);
        // Set delete button to disabled
        $('button.deleteSelectedImages').prop('disabled', true);
        // Set breaking to false
        breaking = false;

        // Do ajax call to get al the images
        $.ajax({
            type: 'POST',
            url: "/ajaximage/getAllDatabaseImages",
            async: true,
            success: function (data) {
                var totalImageTypes = data.result.length;
                if (totalImageTypes > 0) {
                    processLinksDbAllArray(data.result, totalImageTypes);
                } else {
                    resetImageScan();
                }
            },
            error: function (XMLHttpRequest, textStatus, errorThrown) {
                alert(errorThrown);
            }
        });
    });

    /**
     * Check al images on the server (compare with records in the database)
     */
    $("button#startServerScan").on("click", function () {
        // Disable button to prevent multiple clicking
        $(this).prop('disabled', true);
        // Hide the result table, show it on the end
        $('div#image-results-table').hide();
        // Un disable the cancel button
        $('button#cancelImageScan').prop('disabled', false);
        // Set delete button to disabled
        $('button.deleteSelectedImages').prop('disabled', true);
        // Set breaking to false
        breaking = false;

        // Do ajax call to get al the images
        $.ajax({
            type: 'POST',
            url: "/ajaximage/getAllServerImages",
            async: true,
            success: function (data) {
                let totalImageUrls = data.result.length;
                if (totalImageUrls > 0) {
                    processLinksServerAllArray(data.result, totalImageUrls);
                } else {
                    resetImageScan();
                }
            },
            error: function (XMLHttpRequest, textStatus, errorThrown) {
                alert(errorThrown);
            }
        });
    });

    /**
     * Recursively processes an array of link objects, updating the database and UI accordingly.
     * Depending on the state of the `linksArr` array and `tableImages`, it may display the result table, reset image scan, or disable the break button.
     *
     * @param {Array<Object>} linksArr - An array of link objects to be processed.
     * @param {number} totalResult - Total result count to keep track of the processing results.
     * @param {number} index - The current index being processed.
     * @return {void}
     */
    function processLinksDbAllArray(linksArr, totalResult, index) {

        if (linksArr.length <= 0 && tableImages.rows().count() > 0) {
            showResultTable();
        } else if (linksArr.length == 0 && tableImages.rows().count() == 0) {
            resetImageScan();
        }

        if (linksArr.length > 0 && breaking === false) {
            var id = linksArr[0]['id'];
            var name = linksArr[0]['name'];
            var folder = linksArr[0]['folder'];

            var linksArr = $.grep(linksArr, function (e) {
                return e.id != id;
            });
            processLinksDbAllArrayAjax(linksArr, id, name, folder, totalResult, index);
        } else {
            $('button#break').attr('disabled', 'disabled');
            $('button#break').addClass('disabled');
            breaking = false;
        }
    }

    /**
     * Processes an array of links and updates the server with each link.
     * Takes action depending on the state of the links array and other conditions.
     *
     * @param {Array} linksArr - The array of links to be processed. Each link is an object containing a URL property.
     * @param {number} totalResult - A number indicating the total result count for processing.
     * @return {void} This function does not return a value.
     */
    function processLinksServerAllArray(linksArr, totalResult) {
        if (linksArr.length <= 0 && tableImages.rows().count() > 0) {
            showResultTable();
        }  else if (linksArr.length == 0 && tableImages.rows().count() == 0) {
            resetImageScan();
        }

        if (linksArr.length > 0 && breaking === false) {
            var url = linksArr[0]['url'];
            var linksArr = $.grep(linksArr, function (e) {
                return e.url != url;
            });
            processLinksServerAllArrayAjax(linksArr, url, totalResult);
        } else {
            $('button#break').attr('disabled', 'disabled');
            $('button#break').addClass('disabled');
            breaking = false;

        }
    }

    /**
     * Processes a list of links by making an AJAX request to check for their presence in the database,
     * updates a progress bar, and populates a data table with results.
     *
     * @param {Array} linksArr - The array of link objects to be processed.
     * @param {number} id - The ID associated with the link.
     * @param {string} name - The name associated with the link.
     * @param {string} folder - The folder path associated with the link.
     * @param {number} totalResult - The total number of results to be processed.
     * @param {number} index - The current index for tracking progress.
     * @return {void}
     */
    function processLinksDbAllArrayAjax(linksArr, id, name, folder, totalResult, index) {

        var part = 100 / totalResult;
        var progress = (totalResult - linksArr.length) * part;

        $.ajax({
            type: 'POST',
            data: {
                id: id,
                name: name,
                folder: folder
            },
            url: "/ajaximage/checkDatabaseImage",
            async: true,
            success: function (data) {
                let hasResult = false;
                var tableId = totalResult - linksArr.length - 1;
                if (data.succes !== true) {
                    hasResult = true;
                    if (typeof index === 'number') {
                        index++;
                    } else {
                        index = 0
                    }

                    tableImages.row
                        .add([
                            "<input type='checkbox' data-id='" + index + "' name='delete-image[]' value='" + id + "'/>",
                            id,
                            name,
                            folder
                        ])
                        .draw(false);
                }
                $('div#progressBar > div.progress-bar').css('width', progress + '%');
                processLinksDbAllArray(linksArr, totalResult, index);
            },
            error: function (XMLHttpRequest, textStatus, errorThrown) {
                alert(errorThrown);
            }
        });
    }

    /**
     * Processes an array of links by making server-side requests via AJAX, updates the progress bar,
     * and appends new rows to an HTML table with the results.
     *
     * @param {string[]} linksArr - An array containing the links to be processed.
     * @param {string} url - The URL associated with the current link being processed.
     * @param {number} totalResult - The total number of results to process, used for calculating progress.
     * @return {void}
     */
    function processLinksServerAllArrayAjax(linksArr, url, totalResult) {

        let part = 100 / totalResult;
        let progress = (totalResult - linksArr.length) * part;

        var id = totalResult - linksArr.length - 1;
        tableImages.row
            .add([
                "<input type='checkbox' data-id='"+id+"' name='delete-image[]' value='" + url + "'/>",
                url,
                "<img class='img-thumbnail img-responsive' width='200' height='200' src='"+url+"' alt='"+url+"'/>"
            ])
            .draw(false);

        $('div#progressBar > div.progress-bar').css('width', progress + '%');

        processLinksServerAllArray(linksArr, totalResult);
    }

    /**
     * Cancel database image check
     * @type {boolean}
     */
    var breaking = false;
    $("#break").on("click", function () {
        breaking = true;
    });

    /**
     * (Un)Check all checkboxes to delete all images
     */
    $("input[name='delete-all-images']").on("change", function () {
        let atLeastOneIsChecked = $("input[name='delete-all-images']:checked").length > 0;
        if (atLeastOneIsChecked) {
            $('button.deleteSelectedImages').prop("disabled", false);
            $("input[name='delete-image[]']").prop('checked', true);
        } else {
            $('button.deleteSelectedImages').prop("disabled", true);
            $("input[name='delete-image[]']").prop('checked', false);
        }

    });

    /**
     * When a checkbox of an image is (un)checked enable or disable delete button
     */
    $(document).on("click", "input[name='delete-image[]']", function () {
        let atLeastOneIsChecked = $("input[name='delete-image[]']:checked").length > 0;
        if (atLeastOneIsChecked) {
            $('button.deleteSelectedImages').prop("disabled", false);
        } else {
            $('button.deleteSelectedImages').prop("disabled", true);
        }
    });

    /**
     * Delete al selected images from database
     */
    $(document).on("click", "button#deleteSelectedImagesFromDatabase", function () {
        let images = []
        $("input[name='delete-image[]']:checked").each(function(i) {
            if($(this).val() != '') {
                images[i] = [];
                images[i][0] = $(this).data('id');
                images[i][1] = $(this).val();
            }
        });

        $.ajax({
            type: 'POST',
            data: {
                images: images
            },
            url: "/ajaximage/deleteImagesFromDatabase",
            async: true,
            success: function (data) {
                if (data.succes === true) {
                    $.each(data.imageMessages, function( index, imageMessage ) {
                        if (imageMessage.succes) {
                            tableImages.row(imageMessage.rowIndex).remove().draw();
                        }
                    });

                    if (tableImages.rows().count() <= 0){
                        resetImageScan();
                    }
                }
            }
        });
    });

    /**
     * Delete al selected images from server
     */
    $(document).on("click", "button#deleteSelectedImagesFromServer", function () {
        let images = []
        $("input[name='delete-image[]']:checked").each(function(i) {
            if($(this).val() != '') {
                images[i] = [];
                images[i][0] = $(this).data('id');
                images[i][1] = $(this).val();
            }
        });

        $.ajax({
            type: 'POST',
            data: {
                images: images
            },
            url: "/ajaximage/deleteImagesFromServer",
            async: true,
            success: function (data) {
                    $.each(data.result, function( index, value ) {
                        if (value) {
                            tableImages.row(index).remove().draw();
                        }
                    });
                    if (tableImages.rows().count() <= 0){
                        resetImageScan();
                    }
            }
        });
    });

    /**
     * Displays the result table and updates the states of various buttons accordingly.
     *
     * @return {void} This function does not return a value.
     */
    function showResultTable()
    {
        $('div#image-results-table').show();
        $('button#startImageScan').prop('disabled', false);
        $('button#startServerScan').prop('disabled', false);
        $('button#cancelImageScan').prop('disabled', true);
    }

    /**
     * Reset all values to there origin.
     */
    function resetImageScan() {
        breaking = true;
        $('button#startImageScan').prop('disabled', false);
        $('button#startServerScan').prop('disabled', false);
        $('button#cancelImageScan').prop('disabled', true);
        $('div#image-results-table').hide();
        $('button.deleteSelectedImages').prop("disabled", true)
        setTimeout(function () {
            $('tbody#imageResults').empty();
            $('div#progressBar > div.progress-bar').attr('style', "width: 0%");
        }, 250);
    }

    /**
     * Cancel image scan
     */
    $('button#cancelImageScan').click(function () {
        resetImageScan();
    });


    $(document).on("click", "button.delete-fileorfolder", function () {
        let path = $(this).data('path');
        let type = $(this).data('type');
        let index = $(this).data('index');
        $.ajax({
            type: 'POST',
            data: {
                path: path,
                type: type
            },
            url: "/ajaximage/deleteImageOrFolderFromServer",
            async: true,
            success: function (data) {
                if (data.succes) {
                    $("tr#fileRow"+index).fadeOut(300, function(){ $(this).remove();});
                } else {
                    alert(data.errorMessage);
                }
            }
        });

    });


});
