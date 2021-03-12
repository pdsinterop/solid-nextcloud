var launcherApi = {};
var launcher = {};

var loader = function() {
    editor.data.load({});
    window.removeEventListener("load", loader);
}
window.addEventListener("load", loader);

window.addEventListener("simply-content-loaded", function() {
    webId = (function() {
            var data = document.querySelector("script#webId").innerText;
            return JSON.parse(data);
    })();
    storageUrl = (function() {
        var data = document.querySelector("script#storageUrl").innerText;
        var result = JSON.parse(data) + "/";
        return result;
    })();
    api = {
        fetcher : false,
        session : false,
        getFetcher : function() {
            return new Promise(function(resolve, reject) {
                if (!api.fetcher) {
                    api.fetcher = solidAuthFetcher.customAuthFetcher();
                }
                resolve(api.fetcher);
            });
        },
        getSession : function(fetcher) {
            if (!api.session) {
                api.session = solidAuthFetcher.getSession()
                .then(function(session) {
                    if (session && session.loggedIn) {
                        return session;
                    } else {
                        return fetcher.login({
                            webId: webId,
                            // oidcIssuer: 'https://nextcloud.local',
                            redirect: document.location.href
                        });
                    }
                });
            }
            return api.session;
        },
        login : function() {
            return api.getFetcher()
            .then(function(fetcher) {
                return api.getSession(fetcher)
                .then(function() {
                    return fetcher;
                });
            });
        },
        url : storageUrl,
        get : function(path) {
            return api.login()
            .then(function(fetcher) {
                return fetcher.fetch(api.url + path);
            });
        },
        post : function(path, body) {
            return api.login()
            .then(function(fetcher) {
                return fetcher.fetch(api.url + path, {
                    method: "POST",
                    body: body
                });
            });
        },
        put : function(path, body) {
            return api.login()
            .then(function(fetcher) {
                return fetcher.fetch(api.url + path, {
                    method: "PUT",
                    body: body
                });
            });
        },
        delete : function(path) {
            return api.login()
            .then(function(fetcher) {
                return fetcher.fetch(api.url + path, {
                    method: "DELETE"
                });
            });
        },
        patch : function(path, body) {
            return api.login()
            .then(function(fetcher) {
                return fetcher.fetch(api.url + path, {
                    method: "PATCH",
                    body: body,
                    headers: {
                        "Content-type" : "application/sparql-update"
                    }
                });
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
        },
        getPrivateTypeIndex() {
            return api.get("settings/privateTypeIndex.ttl")
            .then(function(response) {
                if (response.status === 200) {
                    return response.text();
                }
                throw new Error("getPrivateTypeIndex failed", response.status);
            })
            .then(function(text) {
                console.log(text);
            });
        },
        createContainer: function(path) {
            return api.get(path)
            .then(function(response) {
                if (response.status != 200) {
                    // not found, try to create it;
                    // Create a dummy file to make sure the container is created.
                    return api.put(path + "/.dummy", "");
                }
            })
            .then(function() {
                return api.get(path);
            });
        },
        createFile : function(filepath, contents) {
            return api.get(filepath)
            .then(function(response) {
                if (response.status != 200) {
                    return api.put(filepath, contents);
                }
            })
            .then(function() {
                return api.get(filepath);
            });
        },
        addPodWidePermissions : function(permissions, origin) {
        },
        addFilePermissions : function(filename, permissions, origin) {
            return api.get(filename + ".acl") // FIXME: find the acl file from the Link header;
            .then(function(response) {
                if (response.status != 200) {
                    // generate an acl for this file;
                    var turtle = '';//@prefix   acl:  <http://www.w3.org/ns/auth/acl#>.';
                } else {
                    // add permissions to the existing file;
                    var turtle = response.text();
                }
                return turtle;
            })
            .then(function(turtle) {
                const { AclParser, Permissions, Agents } = SolidAclParser;
                const { WRITE, APPEND, READ, CONTROL } = Permissions;
                var fileUrl = api.url + filename;
                var aclUrl = api.url + filename + ".acl";
                const parser = new AclParser({ aclUrl, fileUrl});
                const agents = new Agents();
                agents.addOrigin(origin);
                agents.addWebId(webId);
                parser.turtleToAclDoc(turtle)
                .then(function(doc) {
                    var permissionsToAdd = [];
                    permissions.forEach(function(permission) {
                        switch (permission) {
                            case "acl.Append":
                                permissionsToAdd.push(APPEND);
                            break;
                            case "acl.Read":
                                permissionsToAdd.push(READ);
                            break;
                            case "acl.Write":
                                permissionsToAdd.push(WRITE);
                            break;
                            case "acl.Control":
                                permissionsToAdd.push(CONTROL);
                            break;
                        }
                    })
                    doc.addRule(permissionsToAdd, agents);
                    return doc;
                })
                .then(function(doc) {
                    return parser.aclDocToTurtle(doc);
                })
                .then(function(newTurtle) {
                    // console.log('Dit is em!',newTurtle);
                    api.put(filename+".acl", newTurtle);
                });
            });
        },
        addContainerPermissions : function(container, permissions, origin) {
            return api.get(container + "/.acl") // FIXME: find the acl file from the Link header;
            .then(function(response) {
                if (response.status != 200) {
                    // generate an acl for this file;
                    var turtle = '';//@prefix   acl:  <http://www.w3.org/ns/auth/acl#>.';
                } else {
                    // add permissions to the existing file;
                    var turtle = response.text();
                }
                return turtle;
            })
            .then(function(turtle) {
                const { AclParser, Permissions, Agents } = SolidAclParser;
                const { WRITE, APPEND, READ, CONTROL } = Permissions;
                var containerUrl = api.url + container;
                var aclUrl = api.url + container + "/.acl";
                const parser = new AclParser({ aclUrl, containerUrl});
                const agents = new Agents();
                agents.addOrigin(origin);
                agents.addWebId(webId);
                parser.turtleToAclDoc(turtle)
                .then(function(doc) {
                    var permissionsToAdd = [];
                    permissions.forEach(function(permission) {
                        switch (permission) {
                            case "acl.Append":
                                permissionsToAdd.push(APPEND);
                            break;
                            case "acl.Read":
                                permissionsToAdd.push(READ);
                            break;
                            case "acl.Write":
                                permissionsToAdd.push(WRITE);
                            break;
                            case "acl.Control":
                                permissionsToAdd.push(CONTROL);
                            break;
                        }
                    })
                    doc.addDefaultRule(permissionsToAdd, agents);
                    return doc;
                })
                .then(function(doc) {
                    return parser.aclDocToTurtle(doc);
                })
                .then(function(newTurtle) {
                    // console.log('Dit is em!',newTurtle);
                    api.put(container+"/.acl", newTurtle);
                });
            });
        },
        registerClassWithFile : function(resourceClass, filename, public) {
            if (public) {
                typeIndex = "/settings/publicTypeIndex.ttl";
            } else {
                typeIndex = "/settings/privateTypeIndex.ttl";
            }
            return api.get(typeIndex)
            .then(function(response) {
                if (response.status != 200) {
                    // generate a type index
                    var turtle = '';
                } else {
                    // add permissions to the existing file;
                    var turtle = response.text();
                }
                return turtle;
            })
            .then(function(turtle) {
                const { TypeIndexParser, SolidType } = SolidTypeIndexParser;
                var typeIndexUrl = api.url + typeIndex;
                const parser = new TypeIndexParser({ typeIndexUrl });
                const solidType = new SolidType(resourceClass, filename)
                parser.turtleToTypeIndexDoc(turtle)
                .then(function(doc) {
                    doc.addType(solidType);
                    return doc;
                })
                .then(function(doc) {
                    return parser.typeIndexDocToTurtle(doc);
                })
                .then(function(newTurtle) {
                    api.put(typeIndex, newTurtle);
                });
            });
        },
        registerClassWithContainer : function(resourceClass, container, public) {
            if (public) {
                typeIndex = "/settings/publicTypeIndex.ttl";
            } else {
                typeIndex = "/settings/privateTypeIndex.ttl";
            }
            return api.get(typeIndex)
            .then(function(response) {
                if (response.status != 200) {
                    // generate a type index
                    var turtle = '';
                } else {
                    // add permissions to the existing file;
                    var turtle = response.text();
                }
                return turtle;
            })
            .then(function(turtle) {
                const { TypeIndexParser, SolidType } = SolidTypeIndexParser;
                var typeIndexUrl = api.url + typeIndex;
                const parser = new TypeIndexParser({ typeIndexUrl });
                const solidType = new SolidType(resourceClass, undefined, container)
                parser.turtleToTypeIndexDoc(turtle)
                .then(function(doc) {
                    doc.addType(solidType);
                    return doc;
                })
                .then(function(doc) {
                    return parser.typeIndexDocToTurtle(doc);
                })
                .then(function(newTurtle) {
                    api.put(typeIndex, newTurtle);
                });
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
                var launchUrl = el.getAttribute("data-solid-url");
                var app = editor.pageData.apps.filter(function(app) {
                    return app.launchUrl == launchUrl;
                })[0];
                launcher.actions.preparePod(app);
                window.open(app.launchUrl);
            },
            launch : function(el) {
                var launchUrl = el.getAttribute("data-solid-url");
                var app = editor.pageData.apps.filter(function(app) {
                    return app.launchUrl == launchUrl;
                })[0];
                window.open(app.launchUrl);
            },
            profile : function(el) {
                document.location.href = el.href;
            }
        },
        actions: {
            getApps : function() {
                return launcherApi.getApps()
                .then(function(apps) {
                    editor.pageData.apps = apps;
                });
            },
            preparePod : function(appInfo) {
                appInfo.requirements.forEach(function(requirement) {
                    switch(requirement['type']) {
                        case "podWide":
                            // add podwide permissions as requested by this app - add the origin of the app
                            launcher.actions.addPodWidePermissions(requirement.permissions, appInfo.appOrigin);
                        break;
                        case "container":
                            // add the container
                            // add permissions for the container
                            launcher.actions.createContainer(requirement.container);
                            launcher.actions.addContainerPermissions(requirement.container, requirement.permissions, appInfo.appOrigin);
                        break;
                        case "file":
                            // add the file
                            // add permissions to the file
                            launcher.actions.createFile(requirement.filename);
                            launcher.actions.addFilePermissions(requirement.filename, requirement.permissions, appInfo.appOrigin);
                        break;
                        case "class":
                            // add the class in the correct type registry (public = true)
                            if (requirement.filename) {
                                launcher.actions.registerClassWithFile(requirement.class, requirement.filename, requirement.public);
                            } else if (requirement.container) {
                                launcher.actions.registerClassWithContainer(requirement.class, requirement.container, requirement.public);
                            }
                        break;
                        default:
                            // FIXME: unknown requirement, now what?
                        break;
                    }
                });
            },
            createContainer : function(containerPath) {
                console.log("Create container " + containerPath);
                return launcherApi.createContainer(containerPath);
            },
            createFile : function(filePath) {
                console.log("Create file " + filePath);
                return launcherApi.createFile(filePath);
            },
            addPodWidePermissions : function(permissions, origin) {
                console.log("Add pod wide permissions");
                return launcherApi.addPodWidePermissions(permissions, origin);
            },
            addFilePermissions : function(filename, permissions, origin) {
                console.log("Add file permissions");
                return launcherApi.addFilePermissions(filename, permissions, origin);
            },
            addContainerPermissions : function(container, permissions, origin) {
                console.log("Add container permissions");
                return launcherApi.addContainerPermissions(container, permissions, origin);
            },
            registerClassWithFile : function(resourceClass, filename, public) {
                console.log("Register class with file ");
                return launcherApi.registerClassWithFile(resourceClass, filename, public);
            },
            registerClassWithContainer : function(resourceClass, container, public) {
                console.log("Register class with container ");
                return launcherApi.registerClassWithContainer(resourceClass, container, public);
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