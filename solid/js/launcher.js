var launcherApi = {};
var launcher = {};

var loader = function() {
    editor.data.load({});
    window.removeEventListener("load", loader);
}
window.addEventListener("load", loader);

window.addEventListener("simply-content-loaded", function() {
    var api = {
        url : "launcher-api/",
        headers : {
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        },
        encodeGetParams : function(params) {
            if (!params) {
                return "";
            }
            return Object.entries(params).map(function(keyvalue) {
                return "?" + keyvalue.map(encodeURIComponent).join("=")
            }).join("&");
        },
        get : function(endpoint, params={}) {
            if (!params.token && editor.pageData.token) {
                params.token = editor.pageData.token;
            }
            if (params.token) {
                this.headers['Authorization'] = "Bearer " + params.token;
                delete params.token;
            } else {
                delete this.headers.Authentication;
            }
            return fetch(api.url + endpoint + "/" + api.encodeGetParams(params), {
                mode : 'cors',
                headers: this.headers
            });
        },
        post : function(endpoint, params={}) {
            if (!params.token && editor.pageData.token) {
                params.token = editor.pageData.token;
            }
            if (params.token) {
                this.headers['Authorization'] = "Bearer " + params.token;
                delete params.token;
            } else {
                delete this.headers.Authentication;
            }
            return fetch(api.url + endpoint + "/", {
                mode : 'cors',
                headers: this.headers,
                method: "POST",
                body: JSON.stringify(params, null, "\t")
            });
        },
        postRaw : function(endpoint, params={}, body) {
            if (!params.token && editor.pageData.token) {
                params.token = editor.pageData.token;
            }
            if (params.token) {
                this.headers['Authorization'] = "Bearer " + params.token;
                delete params.token;
            } else {
                delete this.headers.Authentication;
            }
            return fetch(api.url + endpoint + "/", {
                mode : 'cors',
                headers: this.headers,
                method: "POST",
                body: body
            });
        },
        put : function(endpoint, params={}) {
            if (!params.token && editor.pageData.token) {
                params.token = editor.pageData.token;
            }
            if (params.token) {
                this.headers['Authorization'] = "Bearer " + params.token;
                delete params.token;
            } else {
                delete this.headers.Authentication;
            }
            return fetch(api.url + endpoint + "/", {
                mode: 'cors',
                headers: this.headers,
                method: "PUT",
                body: JSON.stringify(params, null, "\t")
            });
        },
        delete : function(endpoint, params={}) {
            if (!params.token && editor.pageData.token) {
                params.token = editor.pageData.token;
            }
            if (params.token) {
                this.headers['Authorization'] = "Bearer " + params.token;
                delete params.token;
            } else {
                delete this.headers.Authentication;
            }
            return fetch(api.url + endpoint + "/" + api.encodeGetParams(params), {
                mode : 'cors',
                headers: this.headers,
                method: "DELETE"
            });
        }
    };
    launcherApi = {
        getApps: function() {
            return new Promise(function(resolve, reject) {
                var data = document.querySelector("script#apps").innerText;
                data = JSON.parse(data);
                resolve(data);
            });
        }
    };

    simply.bind = false;

    launcher = simply.app({
        routes: {
            '/#launch/:app' : function(params) {
                editor.pageData.app = params.app;
                editor.pageData.page = "Launch app";
            },
            '/' : function(params) {
                launcher.actions.getApps();  
                editor.pageData.page = "Launcher";
                editor.pageData.pageTitle = "Solid Apps - Nextcloud";
            }
        },
        commands: {
            allowAndLaunch : function(el) {
                // FIXME: run init;
                var launchUrl = el.getAttribute("data-solid-url");
                var app = editor.pageData.apps.filter(function(app) {
                    return app.launchUrl == launchUrl;
                })[0];
                window.open(app.launchUrl);
            },
            launch : function(el) {
                var launchUrl = el.getAttribute("data-solid-url");
                var app = editor.pageData.apps.filter(function(app) {
                    return app.launchUrl == launchUrl;
                })[0];
                window.open(app.launchUrl);
            }
        },
        actions: {
            getApps : function() {
                return launcherApi.getApps()
                .then(function(apps) {
                    editor.pageData.apps = apps;
                });
            }
        }
    });

    function clone(ob) {
        return JSON.parse(JSON.stringify(ob));
    }

    function updateDataSource(name) {
        document.querySelectorAll('[data-simply-data="'+name+'"]').forEach(function(list) {
            editor.list.applyDataSource(list, name);
            list.dataBindingPaused = 0;
        });
    };
});

editor.transformers = {
    "schemaClass" : {
        render : function(data) {
            this.simplyData = data;
            switch (data) {
                case "schema.TextDigitalDocument":
                    return "text files";
                break;
                case "http://www.w3.org/2002/01/bookmark#Bookmark":
                    return "bookmarks";
                break;
                default:
                    return "data";
                break;
            }
        },
        extract : function(data) {
            return this.simplyData;
        }
    },
    "registered" : {
        render: function(data) {
            this.simplyData = data;
            if (data == 1) {
                return "&#10003;"; // checkmark
            } else {
                return "";
            }
        },
        extract: function(data) {
            return this.simplyData;
        }
    },
    "grants" : {
        render : function(data) {
            this.simplyData = data;
            switch (data) {
                case "acl.Read":
                    return "Read";
                break;
                case "acl.Control":
                    return "Control access";
                break;
                case "acl.Write":
                    return "Add and delete";
                break;
                case "acl.Append":
                    return "Add";
                break;
                default:
                    return data;
                break;
            }
        },
        extract : function(data) {
            return this.simplyData;
        }
    }
};

editor.addDataSource("apps", {
    load : function(el, callback) {
        callback(data);
    }
});