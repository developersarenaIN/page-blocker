(function() {
    // Auto-detect base URL for endpoints
    var baseUrl = (function() {
        var scripts = document.getElementsByTagName('script');
        for (var i = 0; i < scripts.length; i++) {
            var src = scripts[i].src;
            if (src && src.indexOf('security_client.js') !== -1) {
                return src.substring(0, src.lastIndexOf('/'));
            }
        }
        return '';
    })();

    var payload = {
        page: window.location.href,
        referrer: document.referrer,
        ua: navigator.userAgent,
        user_id: window.SECURITY_USER_ID || null,
        session_id: window.SECURITY_SESSION_ID || null
    };

    fetch(baseUrl + '/check_access.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
    })
    .then(function(res) { 
        return res.json();
    })
    .then(function(data) {
        console.log('Security check response:', data); // Debug output
        if (data.blocked) {
            window.location.href = baseUrl + '/blocked.html';
        }
    })
    .catch(function(err) {
        console.error('Security check error:', err); // Debug output
    });
})();
