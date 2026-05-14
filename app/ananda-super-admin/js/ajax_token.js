(function() {
    var csrfTokenMeta = document.querySelector("meta[name='ananda-super-admin-csrf-token']");
    if (!csrfTokenMeta) {
        console.error("CSRF meta tag not found!");
        return;
    }
    var csrfToken = csrfTokenMeta.getAttribute("content");

    function csrfSafeMethod(method) {
        return /^(GET|HEAD|OPTIONS)$/i.test(method);
    }

    if (window.jQuery) {
        $.ajaxSetup({
            beforeSend: function(xhr, settings) {
                if (!csrfSafeMethod(settings.type) && !this.crossDomain) {
                    xhr.setRequestHeader("anti-csrf-token", csrfToken);
                }
            }
        });
    } else {
        var originalOpen = XMLHttpRequest.prototype.open;
        var originalSend = XMLHttpRequest.prototype.send;
        XMLHttpRequest.prototype.open = function(method, url, async, user, password) {
            this._method = method;
            originalOpen.call(this, method, url, async, user, password);
        };
        XMLHttpRequest.prototype.send = function(data) {
            if (!csrfSafeMethod(this._method)) {
                this.setRequestHeader("anti-csrf-token", csrfToken);
            }
            originalSend.call(this, data);
        };
    }
})();

