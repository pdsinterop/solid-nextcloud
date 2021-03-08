this.simply = (function(simply, global) {

    simply.view = function(app, view) {

        app.view = view || {};

        var load = function() {
            var data = app.view;
            var path = editor.data.getDataPath(app.container);
            app.view = editor.currentData[path];
            Object.keys(data).forEach(function(key) {
                app.view[key] = data[key];
            });
        };

        if (global.editor && editor.currentData) {
            load();
        } else {
            document.addEventListener('simply-content-loaded', function() {
                load();
            });
        }
        
        return app.view;
    };

    return simply;
})(this.simply || {}, this);
this.simply = (function(simply, global) {

    var routeInfo = [];

    function parseRoutes(routes) {
        var paths = Object.keys(routes);
        var matchParams = /:(\w+|\*)/g;
        var matches, params, path;
        for (var i=0; i<paths.length; i++) {
            path    = paths[i];
            matches = [];
            params  = [];
            do {
                matches = matchParams.exec(path);
                if (matches) {
                    params.push(matches[1]);
                }
            } while(matches);
            routeInfo.push({
                match:  new RegExp(path.replace(/:\w+/g, '([^/]+)').replace(/:\*/, '(.*)')),
                params: params,
                action: routes[path]
            });
        }
    }

    var linkHandler = function(evt) {
        if (evt.ctrlKey) {
            return;
        }
        if (evt.which != 1) {
            return; // not a 'left' mouse click
        }
        var link = evt.target;
        while (link && link.tagName!='A') {
            link = link.parentElement;
        }
        if (link 
            && link.pathname 
            && link.hostname==document.location.hostname 
            && !link.link
            && !link.dataset.simplyCommand
            && simply.route.has(link.pathname+link.hash)
        ) {
            simply.route.goto(link.pathname+link.hash);
            evt.preventDefault();
            return false;
        }
    };

    simply.route = {
        handleEvents: function() {
			var lastSeen = null;
            global.addEventListener('popstate', function() {
				lastSeen = document.location.pathname+document.location.hash;
                simply.route.match(lastSeen);
            });
			global.addEventListener('hashchange', function() {
				// IE and Edge<14 don't fire popstate for hashchanges, this catches that
				if (document.location.pathname+document.location.hash !== lastSeen) {
					lastSeen = document.location.pathname+document.location.hash;
	                simply.route.match(lastSeen);
				}
			});
            document.addEventListener('click', linkHandler);
        },
        load: function(routes) {
            parseRoutes(routes);
        },
        match: function(path, options) {
            var matches;
            for ( var i=0; i<routeInfo.length; i++) {
                if (path[path.length-1]!='/') {
                    matches = routeInfo[i].match.exec(path+'/');
                    if (matches) {
                        path+='/';
                        history.replaceState({}, '', path);
                    }
                }
                matches = routeInfo[i].match.exec(path);
                if (matches && matches.length) {
                    var params = {};
                    routeInfo[i].params.forEach(function(key, i) {
                        if (key=='*') {
                            key = 'remainder';
                        }
                        params[key] = matches[i+1];
                    });
                    Object.assign(params, options);
                    return routeInfo[i].action.call(simply.route, params);
                }
            }
        },
        goto: function(path) {
            history.pushState({},'',path);
            return simply.route.match(path);
        },
        has: function(path) {
            for ( var i=0; i<routeInfo.length; i++) {
                var matches = routeInfo[i].match.exec(path);
                if (matches && matches.length) {
                    return true;
                }
            }
            return false;
        }
    };

    return simply;

})(this.simply || {}, this);
this.simply = (function(simply, global) {

	simply.resize = function(app, config) {
		if (!config) {
			config = {};
		}
		if (!config.sizes) {
        	config.sizes     = {
	            'simply-tiny'   : 0,
	            'simply-xsmall' : 480,
	            'simply-small'  : 768,
	            'simply-medium' : 992,
	            'simply-large'  : 1200
	        };
		}

        var lastSize = 0;
        function resizeSniffer() {
            var size = app.container.getBoundingClientRect().width;
            if ( lastSize==size ) {
                return;
            }
            lastSize  = size;
            var sizes = Object.keys(config.sizes);
            var match = sizes.pop();
            while (match) {
                if ( size<config.sizes[match] ) {
                    if ( app.container.classList.contains(match)) {
                        app.container.classList.remove(match);
                    }
                } else {
                    if ( !app.container.classList.contains(match) ) {
                        app.container.classList.add(match);
                    }
                    break;
                }
                match = sizes.pop();
            }
            while (match) {
                if ( app.container.classList.contains(match)) {
                    app.container.classList.remove(match);
                }
                match=sizes.pop();
            }
            var toolbars = app.container.querySelectorAll('.simply-toolbar');
            [].forEach.call(toolbars, function(toolbar) {
                toolbar.style.transform = '';
            });
        }

        if ( global.attachEvent ) {
            app.container.attachEvent('onresize', resizeSniffer);
        } else {
            global.setInterval(resizeSniffer, 200);
        }

        if ( simply.toolbar ) {
            var toolbars = app.container.querySelectorAll('.simply-toolbar');
            [].forEach.call(toolbars, function(toolbar) {
                simply.toolbar.init(toolbar);
                if (simply.toolbar.scroll) {
                    simply.toolbar.scroll(toolbar);
                }
            });
        }

		return resizeSniffer;
	};

	return simply;

})(this.simply || {}, this);this.simply = (function(simply, global) {

//    var templates = new WeakMap();

    simply.render = function(options) {
        if (!options) {
            options = {};
        }
        options = Object.assign({
            attribute: 'data-simply-field',
            selector: '[data-simply-field]',
            twoway: true,
            model: {}
        }, options);

        options.fieldTypes = Object.assign({
            '*': {
                set: function(value) {
                    this.innerHTML = value;
                },
                get: function() {
                    return this.innerHTML;
                }
            },
            'input,textarea,select': {
                init: function(binding) {
                    this.addEventListener('input', function() {
                        if (binding.observing) {
                            this.dispatchEvent(new Event('simply.bind.update', {
                                bubbles: true,
                                cancelable: true
                            }));
                        }
                    });
                },
                set: function(value) {
                    this.value = value;
                },
                get: function() {
                    return this.value;
                }
            },
            'input[type=radio]': {
                init: function(binding) {
                    this.addEventListener('change', function() {
                        if (binding.observing) {
                            this.dispatchEvent(new Event('simply.bind.update', {
                                bubbles: true,
                                cancelable: true
                            }));
                        }
                    });
                },
                set: function(value) {
                    this.checked = (value==this.value);
                },
                get: function() {
                    var checked;
                    if (this.form) {
                        return this.form[this.name].value;
                    } else if (checked=document.body.querySelector('input[name="'+this.name+'"][checked]')) { 
                        return checked.value;
                    } else {
                        return null;
                    }
                }
            },
            'input[type=checkbox]': {
                init: function(binding) {
                    this.addEventListener('change', function() {
                        if (binding.observing) {
                            this.dispatchEvent(new Event('simply.bind.update', {
                                bubbles: true,
                                cancelable: true
                            }));
                        }
                    });
                },
                set: function(value) {
                    this.checked = (value.checked);
                    this.value = value.value;
                },
                get: function() {
                    return {
                        checked: this.checked,
                        value: this.value
                    };
                }
            },
            'select[multiple]': {
                init: function(binding) {
                    this.addEventListener('change', function() {
                        if (binding.observing) {
                            this.dispatchEvent(new Event('simply.bind.update', {
                                bubbles: true,
                                cancelable: true
                            }));
                        }
                    });
                },
                set: function(value) {
                    for (var i=0,l=this.options.length;i<l;i++) {
                        this.options[i].selected = (value.indexOf(this.options[i].value)>=0);
                    }
                },
                get: function() {
                    return this.value;
                }
            },
//            '[data-simply-content="template"]': {
//                 allowNesting: true
//            },
        }, options.fieldTypes);

        return options;
    };

    return simply;
})(this.simply || {}, this);
this.simply = (function(simply) {

    simply.path = {
        get: function(model, path) {
            if (!path) {
                return model;
            }
            return path.split('.').reduce(function(acc, name) {
                return (acc && acc[name] ? acc[name] : null);
            }, model);
        },
        set: function(model, path, value) {
            var lastName   = simply.path.pop(path);
            var parentPath = simply.path.parent(path);
            var parentOb   = simply.path.get(model, parentPath);
            parentOb[lastName] = value;
        },
        pop: function(path) {
            return path.split('.').pop();
        },
        push: function(path, name) {
            return (path ? path + '.' : '') + name;
        },
        parent: function(path) {
            var p = path.split('.');
            p.pop();
            return p.join('.');
        },
        parents: function(path) {
            var result = [];
            path.split('.').reduce(function(acc, name) {
                acc.push( (acc.length ? acc[acc.length-1] + '.' : '') + name );
                return acc;
            },result);
            return result;
        }
    };

    return simply;
})(this.simply || {});
/**
 * simply.observe
 * This component lets you observe changes in a json compatible data structure
 * It doesn't support linking the same object multiple times
 * It doesn't register deletion of properties using the delete keyword, assign
 * null to the property instead.
 * It doesn't register addition of new properties.
 * It doesn't register directly assigning new entries in an array on a previously
 * non-existant index.
 *
 * usage:
 *
 * (function) simply.observe( (object) model, (string) path, (function) callback)
 *
 * var model = { foo: { bar: 'baz' } };
 * var removeObserver = simply.observe(model, 'foo.bar', function(value, sourcePath) {
 *   console.log(sourcePath+': '+value);
 * };
 *
 * The function returns a function that removes the observer when called.
 *
 * The component can observe in place changes in arrays, either by changing
 * an item in a specific index, by calling methods on the array that change
 * the array in place or by reassigning the array with a new value.
 *
 * The sourcePath contains the exact entry that was changed, the value is the
 * value for the path passed to simply.observe.
 * If an array method was called that changes the array in place, the sourcePath
 * also contains that method and its arguments JSON serialized.
 *
 * sourcePath parts are always seperated with '.', even for array indexes.
 * so if foo = [ 'bar' ], the path to 'bar' would be 'foo.0'
 */

 /*
 FIXME: child properties added after initial observe() call aren't added to the
 childListeners. onMissingChildren can't then find them.
 TODO: onMissingChildren must loop through all fields to get only the direct child
properties for a given parent, keep seperate index for this?
 */

this.simply = (function (simply, global) {
    var changeListeners = new WeakMap();
    var parentListeners = new WeakMap();
    var childListeners = new WeakMap();
    var changesSignalled = {};
    var observersPaused = 0;

    function signalChange(model, path, value, sourcePath) {
        if (observersPaused) {
            return;
        }

        sourcePath = sourcePath ? sourcePath : path;
        changesSignalled = {};

        var signalRecursion = function(model, path, value, sourcePath) {
            if (changeListeners.has(model) && changeListeners.get(model)[path]) {
                // changeListeners[model][path] contains callback methods
                changeListeners.get(model)[path].forEach(function(callback) {
                    changesSignalled[path] = true;
                    callback(value, sourcePath);
                });
            }
        };

        //TODO: check if this is correct
        //previous version only triggered parentListeners when no changeListeners were
        //triggered. that created problems with arrays. make an exhaustive unit test.
        signalRecursion(model, path, value, sourcePath);
        
        if (parentListeners.has(model) && parentListeners.get(model)[path]) {
            // parentListeners[model][path] contains child paths to signal change on
            // if a parent object is changed, this signals the change to the child objects
            parentListeners.get(model)[path].forEach(function(childPath) {
                if (!changesSignalled[childPath]) {
                    var value = getByPath(model, childPath);
                    if (value) {
                        attach(model, childPath);
                    }
                    signalRecursion(model, childPath, value, sourcePath);
                    changesSignalled[childPath] = true;
                }
            });
        }

        if (childListeners.has(model) && childListeners.get(model)[path]) {
            // childListeners[model][path] contains parent paths to signal change on
            // if a child object is changed, this signals the change to the parent objects
            childListeners.get(model)[path].forEach(function(parentPath) {
                if (!changesSignalled[parentPath]) {
                    var value = getByPath(model, parentPath);
                    signalRecursion(model, parentPath, value, sourcePath);
                    changesSignalled[parentPath] = true;
                    // check if the parent object still has this child property
                    //FIXME: add a setter trigger here to restore observers once the child property get set again

                }
            });
        }

    }

    function getByPath(model, path) {
        var parts = path.split('.');
        var curr = model;
        do {
            curr = curr[parts.shift()];
        } while (parts.length && curr);
        return curr;
    }

    function parent(path) {
        var parts = path.split('.');
        parts.pop();
        return parts.join('.');
    }
    
    function head(path) {
        return path.split('.').shift();
    }

    function onParents(model, path, callback) {
        var parent = '';
        var parentOb = model;
        var parents = path.split('.');
        do {
            var head = parents.shift();
            if (parentOb && typeof parentOb[head] != 'undefined') {
                callback(parentOb, head, (parent ? parent + '.' + head : head));
                parentOb = parentOb[head];
            }
            parent = (parent ? parent + '.' + head : head );
        } while (parents.length);
    }

    function onChildren(model, path, callback) {
        var onChildObjects = function(object, path, callback) {
            if (typeof object != 'object' || object == null) {
                return;
            }
            if (Array.isArray(object)) {
                return;
            }
            // register the current keys
            Object.keys(object).forEach(function(key) {
                callback(object, key, path+'.'+key);
                onChildObjects(object[key], path+'.'+key, callback);
            });
        };
        var parent = getByPath(model, path);
        onChildObjects(parent, path, callback);
    }

    function onMissingChildren(model, path, callback) {
        var allChildren = Object.keys(childListeners.get(model) || []).filter(function(childPath) {
            return childPath.substr(0, path.length)==path && childPath.length>path.length;
        });
        if (!allChildren.length) {
            return;
        }
        var object = getByPath(model, path);
        var keysSeen = {};
        allChildren.forEach(function(childPath) {
            var key = head(childPath.substr(path.length+1));
            if (typeof object[key] == 'undefined') {
                if (!keysSeen[key]) {
                    callback(object, key, path+'.'+key);
                    keysSeen[key] = true;
                }
            } else {
                onMissingChildren(model, path+'.'+key, callback);
            }
        });
    }

    function addChangeListener(model, path, callback) {
        if (!changeListeners.has(model)) {
            changeListeners.set(model, {});
        }
        if (!changeListeners.get(model)[path]) {
            changeListeners.get(model)[path] = [];
        }
        changeListeners.get(model)[path].push(callback);

        if (!parentListeners.has(model)) {
            parentListeners.set(model, {});
        }
        var parentPath = parent(path);
        onParents(model, parentPath, function(parentOb, key, currPath) {
            if (!parentListeners.get(model)[currPath]) {
                parentListeners.get(model)[currPath] = [];
            }
            parentListeners.get(model)[currPath].push(path);
        });

        if (!childListeners.has(model)) {
            childListeners.set(model, {});
        }
        onChildren(model, path, function(childOb, key, currPath) {
            if (!childListeners.get(model)[currPath]) {
                childListeners.get(model)[currPath] = [];
            }
            childListeners.get(model)[currPath].push(path);
        });
    }

    function removeChangeListener(model, path, callback) {
        if (!changeListeners.has(model)) {
            return;
        }
        if (changeListeners.get(model)[path]) {
            changeListeners.get(model)[path] = changeListeners.get(model)[path].filter(function(f) {
                return f != callback;
            });
        }
    }

    function pauseObservers() {
        observersPaused++;
    }

    function resumeObservers() {
        observersPaused--;
    }

    function attach(model, path, options) {

        var attachArray = function(object, path) {
            var desc = Object.getOwnPropertyDescriptor(object, 'push');
            if (!desc || desc.configurable) {
                for (var f of ['push','pop','reverse','shift','sort','splice','unshift','copyWithin']) {
                    (function(f) {
                        try {
                            Object.defineProperty(object, f, {
                                value: function() {
                                    pauseObservers();
                                    var result = Array.prototype[f].apply(this, arguments);
                                    attach(model, path);
                                    var args = [].slice.call(arguments).map(function(arg) {
                                        return JSON.stringify(arg);
                                    });
                                    resumeObservers();
                                    signalChange(model, path, this, path+'.'+f+'('+args.join(',')+')');
                                    return result;
                                },
                                readable: false,
                                enumerable: false,
                                configurable: false
                            });
                        } catch(e) {
                            console.error('simply.observer: Error: Couldn\'t redefine array method '+f+' on '+path, e);
                        }
                    }(f));
                }
                for (var i=0, l=object.length; i<l; i++) {
                    //FIXME: options becomes undefined here somewhere
//                    if (options.skipArray) {
                        addSetter(object, i, path+'.'+i);
//                    } else {
//                        attach(model, path+'.'+i, options);
//                    }
                }
            }
        };

        var addSetTrigger = function(object, key, currPath) {
            Object.defineProperty(object, key, {
                set: function(value) {
                    addSetter(object, key, currPath);
                    object[key] = value;
                },
                configurable: true,
                readable: false,
                enumerable: false
            });
        };

        var addSetter = function(object, key, currPath) {
            if (Object.getOwnPropertyDescriptor(object, key).configurable) {
                // assume object keys are only unconfigurable if the
                // following code has already been run on this property
                var _value = object[key];
                Object.defineProperty(object, key, {
                    set: function(value) {
                        _value = value;
                        signalChange(model, currPath, value);
                        if (value!=null) {
                            onChildren(model, currPath, addSetter);
                            onMissingChildren(model, currPath, addSetTrigger);
                        }
                    },
                    get: function() {
                        return _value;
                    },
                    configurable: false,
                    readable: true,
                    enumerable: true
                });
            }
            if (Array.isArray(object[key])) {
                attachArray(object[key], currPath, options);
            }
        };

        onParents(model, path, addSetter);
        onChildren(model, path, addSetter);
    }

    // FIXME: if you remove a key by reassigning the parent object
    // and then assign that missing key a new value
    // the observer doesn't get triggered
    // var model = { foo: { bar: 'baz' } };
    // simply.observer(model, 'foo.bar', ...)
    // model.foo = { }
    // model.foo.bar = 'zab'; // this should trigger the observer but doesn't

    simply.observe = function(model, path, callback, options) {
        if (!path) {
            var keys = Object.keys(model);
            keys.forEach(function(key) {
                attach(model, key, options);
                addChangeListener(model, key, callback);
            }); 
            return function() {
                keys.forEach(function(key) {
                    removeChangeListener(model, key, callback);
                });
            };
        } else {
            attach(model, path, options);
            addChangeListener(model, path, callback);
            return function() {
                removeChangeListener(model, path, callback);
            };
        }
    };

    return simply;
})(this.simply || {}, this);this.simply = (function (simply, global) {

    var throttle = function( callbackFunction, intervalTime ) {
        var eventId = 0;
        return function() {
            var myArguments = arguments;
            var me = this;
            if ( eventId ) {
                return;
            } else {
                eventId = global.setTimeout( function() {
                    callbackFunction.apply(me, myArguments);
                    eventId = 0;
                }, intervalTime );
            }
        };
    };

    var runWhenIdle = (function() {
        if (global.requestIdleCallback) {
            return function(callback) {
                global.requestIdleCallback(callback, {timeout: 500});
            };
        }
        return global.requestAnimationFrame;
    })();

    var rebaseHref = function(relative, base) {
        if (/^[a-z-]*:?\//.test(relative)) {
            return relative; // absolute href, no need to rebase
        }

        var stack = base.split('/'),
            parts = relative.split('/');
        stack.pop(); // remove current file name (or empty string)
        for (var i=0; i<parts.length; i++) {
            if (parts[i] == '.')
                continue;
            if (parts[i] == '..')
                stack.pop();
            else
                stack.push(parts[i]);
        }
        return stack.join('/');
    };

    var observer, loaded = {};
    var head = document.documentElement.querySelector('head');
    var currentScript = document.currentScript;

    var waitForPreviousScripts = function() {
        // because of the async=false attribute, this script will run after
        // the previous scripts have been loaded and run
        // simply.include.next.js only fires the simply-next-script event
        // that triggers the Promise.resolve method
        return new Promise(function(resolve) {
            var next = document.createElement('script');
            next.src = rebaseHref('simply.include.next.js', currentScript.src);
            next.async = false;
            document.addEventListener('simply-include-next', function() {
                head.removeChild(next);
                resolve();
            }, { once: true, passive: true});
            head.appendChild(next);
        });
    };

    var scriptLocations = [];

    simply.include = {
        scripts: function(scripts, base) {
            var arr = [];
            for(var i = scripts.length; i--; arr.unshift(scripts[i]));
            var importScript = function() {
                var script = arr.shift();
                if (!script) {
                    return;
                }
                var attrs  = [].map.call(script.attributes, function(attr) {
                    return attr.name;
                });
                var clone  = document.createElement('script');
                attrs.forEach(function(attr) {
                    clone.setAttribute(attr, script[attr]);
                });
                clone.removeAttribute('data-simply-location');
                if (!clone.src) {
                    // this is an inline script, so copy the content and wait for previous scripts to run
                    clone.innerHTML = script.innerHTML;
                    waitForPreviousScripts()
                        .then(function() {
                            var node = scriptLocations[script.dataset.simplyLocation];
                            node.parentNode.insertBefore(clone, node);
                            node.parentNode.removeChild(node);
                            importScript();
                        });
                } else {
                    clone.src = rebaseHref(clone.src, base);
                    if (!clone.hasAttribute('async') && !clone.hasAttribute('defer')) {
                        clone.async = false; //important! do not use clone.setAttribute('async', false) - it has no effect
                    }
                    var node = scriptLocations[script.dataset.simplyLocation];
                    node.parentNode.insertBefore(clone, node);
                    node.parentNode.removeChild(node);
                    loaded[clone.src]=true;
                    importScript();
                }
            };
            if (arr.length) {
                importScript();
            }
        },
        html: function(html, link) {
            var fragment = document.createRange().createContextualFragment(html);
            var stylesheets = fragment.querySelectorAll('link[rel="stylesheet"],style');
            // add all stylesheets to head
            [].forEach.call(stylesheets, function(stylesheet) {
                if (stylesheet.href) {
                    stylesheet.href = rebaseHref(stylesheet.href, link.href);
                }
                head.appendChild(stylesheet);
            });
            // remove the scripts from the fragment, as they will not run in the
            // order in which they are defined
            var scriptsFragment = document.createDocumentFragment();
            // FIXME: this loses the original position of the script
            // should add a placeholder so we can reinsert the clone
            var scripts = fragment.querySelectorAll('script');
            [].forEach.call(scripts, function(script) {
                var placeholder = document.createComment(script.src || 'inline script');
                script.parentNode.insertBefore(placeholder, script);
                script.dataset.simplyLocation = scriptLocations.length;
                scriptLocations.push(placeholder);
                scriptsFragment.appendChild(script);
            });
            // add the remainder before the include link
            link.parentNode.insertBefore(fragment, link ? link : null);
            global.setTimeout(function() {
                if (global.editor && global.editor.data && fragment.querySelector('[data-simply-field],[data-simply-list]')) {
                    //TODO: remove this dependency and let simply.bind listen for dom node insertions (and simply-edit.js use simply.bind)
                    global.editor.data.apply(editor.currentData, document);
                }
                simply.include.scripts(scriptsFragment.childNodes, link ? link.href : global.location.href );
            }, 10);
        }
    };

    var included = {};
    var includeLinks = function(links) {
        // mark them as in progress, so handleChanges doesn't find them again
        var remainingLinks = [].reduce.call(links, function(remainder, link) {
            if (link.rel=='simply-include-once' && included[link.href]) {
                link.parentNode.removeChild(link);
            } else {
                included[link.href]=true;
                link.rel = 'simply-include-loading';
                remainder.push(link);
            }
            return remainder;
        }, []);
        [].forEach.call(remainingLinks, function(link) {
            if (!link.href) {
                return;
            }
            // fetch the html
            fetch(link.href)
                .then(function(response) {
                    if (response.ok) {
                        console.log('simply-include: loaded '+link.href);
                        return response.text();
                    } else {
                        console.log('simply-include: failed to load '+link.href);
                    }
                })
                .then(function(html) {
                    // if succesfull import the html
                    simply.include.html(html, link);
                    // remove the include link
                    link.parentNode.removeChild(link);
                });
        });
    };

    var handleChanges = throttle(function() {
        runWhenIdle(function() {
            var links = document.querySelectorAll('link[rel="simply-include"],link[rel="simply-include-once"]');
            if (links.length) {
                includeLinks(links);
            }
        });
    });

    var observe = function() {
        observer = new MutationObserver(handleChanges);
        observer.observe(document, {
            subtree: true,
            childList: true,
        });
    };

    observe();

    return simply;

})(this.simply || {}, this);
this.simply = (function(simply, global) {

    var defaultCommands = {
        'simply-hide': function(el, value) {
            var target = this.app.get(value);
            if (target) {
                this.action('simply-hide',target);
            }
        },
        'simply-show': function(el, value) {
            var target = this.app.get(value);
            if (target) {
                this.action('simply-show',target);
            }
        },
        'simply-select': function(el, value) {
            var group = el.dataset.simplyGroup;
            var target = this.app.get(value);
            var targetGroup = (target ? target.dataset.simplyGroup : null);
            this.action('simply-select', el, group, target, targetGroup);
        },
        'simply-toggle-select': function(el, value) {
            var group = el.dataset.simplyGroup;
            var target = this.app.get(value);
            var targetGroup = (target ? target.dataset.simplyTarget : null);
            this.action('simply-toggle-select',el,group,target,targetGroup);
        },
        'simply-toggle-class': function(el, value) {
            var target = this.app.get(el.dataset.simplyTarget);
            this.action('simply-toggle-class',el,value,target);
        },
        'simply-deselect': function(el, value) {
            var target = this.app.get(value);
            this.action('simply-deselect',el,target);
        },
        'simply-fullscreen': function(el, value) {
            var target = this.app.get(value);
            this.action('simply-fullscreen',target);
        }
    };


    var handlers = [
        {
            match: 'input,select,textarea',
            get: function(el) {
                return el.dataset.simplyValue || el.value;
            },
            check: function(el, evt) {
                return evt.type=='change' || (el.dataset.simplyImmediate && evt.type=='input');
            }
        },
        {
            match: 'a,button',
            get: function(el) {
                return el.dataset.simplyValue || el.href || el.value;
            },
            check: function(el,evt) {
                return evt.type=='click' && evt.ctrlKey==false && evt.button==0;
            }
        },
        {
            match: 'form',
            get: function(el) {
                var data = {};
                [].forEach.call(el.elements, function(el) {
					if (el.tagName=='INPUT' && (el.type=='checkbox' || el.type=='radio')) {
						if (!el.checked) {
							return;
						}
					}
					if (typeof data[el.name] == 'undefined') {
	                    data[el.name] = el.value;
					} else if (Array.isArray(data[el.name])) {
						data[el.name].push(el.value);
					} else {
						data[el.name] = [ data[el.name], el.value ];
					}
                });
                return data;//new FormData(el);
            },
            check: function(el,evt) {
                return evt.type=='submit';
            }
        }
    ];

    var fallbackHandler = {
        get: function(el) {
            return el.dataset.simplyValue;
        },
        check: function(el, evt) {
            return evt.type=='click' && evt.ctrlKey==false && evt.button==0;
        }
    };

    function getCommand(evt) {
        var el = evt.target.closest('[data-simply-command]');
        if (el) {
            var matched = false;
            for (var i=handlers.length-1; i>=0; i--) {
                if (el.matches(handlers[i].match)) {
                    matched = true;
                    if (handlers[i].check(el, evt)) {
                        return {
                            name:   el.dataset.simplyCommand,
                            source: el,
                            value:  handlers[i].get(el)
                        };
                    }
                }
            }
            if (!matched && fallbackHandler.check(el,evt)) {
                return {
                    name:   el.dataset.simplyCommand,
                    source: el,
                    value: fallbackHandler.get(el)
                };
            }
        }
        return null;
    }

    simply.command = function(app, inCommands) {

        var commands = Object.create(defaultCommands);
        for (var i in inCommands) {
            commands[i] = inCommands[i];
        }

        commands.app = app;

        commands.action = function(name) {
            var params = Array.prototype.slice.call(arguments);
            params.shift();
            return app.actions[name].apply(app.actions,params);
        };

        commands.call = function(name) {
            var params = Array.prototype.slice.call(arguments);
            params.shift();
            return this[name].apply(this,params);            
        };

        commands.appendHandler = function(handler) {
            handlers.push(handler);
        };

        commands.prependHandler = function(handler) {
            handlers.unshift(handler);
        };

        var commandHandler = function(evt) {
            var command = getCommand(evt);
            if ( command ) {
                if (!commands[command.name]) {
                    console.error('simply.command: undefined command '+command.name, command.source);
                } else {
                    commands.call(command.name, command.source, command.value);
                    evt.preventDefault();
                    evt.stopPropagation();
                    return false;
                }
            }
        };

        app.container.addEventListener('click', commandHandler);
        app.container.addEventListener('submit', commandHandler);
        app.container.addEventListener('change', commandHandler);
        app.container.addEventListener('input', commandHandler);

        return commands;
    };

    return simply;
    
})(this.simply || {}, this);
this.simply = (function(simply, global) {

    var knownCollections = {};
    
    simply.collect = {
        addListener: function(name, callback) {
            if (!knownCollections[name]) {
                knownCollections[name] = [];
            }
            if (knownCollections[name].indexOf(callback) == -1) {
                knownCollections[name].push(callback);
            }
        },
        removeListener: function(name, callback) {
            if (knownCollections[name]) {
                var index = knownCollections[name].indexOf(callback);
                if (index>=0) {
                    knownCollections[name].splice(index, 1);
                }
            }
        },
        update: function(element, value) {
            element.value = value;
            element.dispatchEvent(new Event('change', {
                bubbles: true,
                cancelable: true
            }));
        }
    };

    function findCollection(el) {
        while (el && !el.dataset.simplyCollection) {
            el = el.parentElement;
        }
        return el;
    }
    
    document.addEventListener('change', function(evt) {
        var root = null;
        var name = '';
        if (evt.target.dataset.simplyElement) {
            root = findCollection(evt.target);
            if (root && root.dataset) {
                name = root.dataset.simplyCollection;
            }
        }
        if (name && knownCollections[name]) {
            var inputs = root.querySelectorAll('[data-simply-element]');
            var elements = [].reduce.call(inputs, function(elements, input) {
                elements[input.dataset.simplyElement] = input;
                return elements;
            }, {});
            for (var i=knownCollections[name].length-1; i>=0; i--) {
                var result = knownCollections[name][i].call(evt.target.form, elements);
                if (result === false) {
                    break;
                }
            }
        }
    }, true);

    return simply;

})(this.simply || {}, this);
this.simply = (function(simply, global) {
    if (!simply.observe) {
        console.error('Error: simply.bind requires simply.observe');
        return simply;
    }

    function getByPath(model, path) {
        var parts = path.split('.');
        var curr = model;
        do {
            curr = curr[parts.shift()];
        } while (parts.length && curr);
        return curr;
    }

    function setByPath(model, path, value) {
        var parts = path.split('.');
        var curr = model;
        while (parts.length>1 && curr) {
            var key = parts.shift();
            if (typeof curr[key] == 'undefined' || curr[key]==null) {
                curr[key] = {};
            }
            curr = curr[key];
        }
        curr[parts.shift()] = value;
    }

    function setValue(el, value, binding) {
        if (el!=focusedElement) {
            var fieldType = getFieldType(binding.fieldTypes, el);
            if (fieldType) {
                fieldType.set.call(el, (typeof value != 'undefined' ? value : ''), binding);
                el.dispatchEvent(new Event('simply.bind.resolved', {
                    bubbles: true,
                    cancelable: false
                }));
            }
        }
    }

    function getValue(el, binding) {
        var setters = Object.keys(binding.fieldTypes);
        for(var i=setters.length-1;i>=0;i--) {
            if (el.matches(setters[i])) {
                return binding.fieldTypes[setters[i]].get.call(el);
            }
        }
    }

    function getFieldType(fieldTypes, el) {
        var setters = Object.keys(fieldTypes);
        for(var i=setters.length-1;i>=0;i--) {
            if (el.matches(setters[i])) {
                return fieldTypes[setters[i]];
            }
        }
        return null;
    }

    function getPath(el, attribute) {
        var attributes = attribute.split(',');
        for (var attr of attributes) {
            if (el.hasAttribute(attr)) {
                return el.getAttribute(attr);
            }
        }
        return null;
    }

    function throttle( callbackFunction, intervalTime ) {
        var eventId = 0;
        return function() {
            var myArguments = arguments;
            var me = this;
            if ( eventId ) {
                return;
            } else {
                eventId = global.setTimeout( function() {
                    callbackFunction.apply(me, myArguments);
                    eventId = 0;
                }, intervalTime );
            }
        };
    }

    var runWhenIdle = (function() {
        if (global.requestIdleCallback) {
            return function(callback) {
                global.requestIdleCallback(callback, {timeout: 500});
            };
        }
        return global.requestAnimationFrame;
    })();

    function Binding(config, force) {
        this.config = config;
        if (!this.config) {
            this.config = {};
        }
        if (!this.config.model) {
            this.config.model = {};
        }
        if (!this.config.attribute) {
            this.config.attribute = 'data-simply-bind';
        }
        if (!this.config.selector) {
            this.config.selector = '[data-simply-bind]';
        }
        if (!this.config.container) {
            this.config.container = document;
        }
        if (typeof this.config.twoway == 'undefined') {
            this.config.twoway = true;
        }
        this.fieldTypes = {
            '*': {
                set: function(value) {
                    this.innerHTML = value;
                },
                get: function() {
                    return this.innerHTML;
                }
            }
        };
        if (this.config.fieldTypes) {
            Object.assign(this.fieldTypes, this.config.fieldTypes);
        }
        this.attach(this.config.container.querySelectorAll(this.config.selector), this.config.model, force);
        if (this.config.twoway) {
            var self = this;
            var observer = new MutationObserver(
                throttle(function() {
                    runWhenIdle(function() {
                        self.attach(self.config.container.querySelectorAll(self.config.selector), self.config.model);
                    });
                })
            );
            observer.observe(this.config.container, {
                subtree: true,
                childList: true
            });
        }
    }

    var focusedElement = null;
    var initialized = new WeakMap();
    var observers = new WeakMap();
    var observersPaused = 0;

    Binding.prototype.attach = function(el, model, force) {
        var illegalNesting = function() {
            return (!force && el.parentElement && el.parentElement.closest(self.config.selector));
        };

        var attachElement = function(jsonPath) {
            el.dataset.simplyBound = true;
            initElement(el);
            setValue(el, getByPath(model, jsonPath), self);
            simply.observe(model, jsonPath, function(value) {
                if (el != focusedElement) {
                    setValue(el, value, self);
                }
            });
        };

        var addMutationObserver = function(jsonPath) {
            if (el.dataset.simplyList) {
                return;
            }
            var update = throttle(function() {
                runWhenIdle(function() {
                    var v = getValue(el, self);
                    var s = getByPath(model, jsonPath);
                    if (v != s) {
                        focusedElement = el;
                        setByPath(model, jsonPath, v);
                        focusedElement = null;
                    }
                });
            }, 250);
            var observer = new MutationObserver(function() {
                if (observersPaused) {
                    return;
                }
                update();
            });
            observer.observe(el, {
                characterData: true,
                subtree: true,
                childList: true,
                attributes: true
            });
            if (!observers.has(el)) {
                observers.set(el, []);
            }
            observers.get(el).push(observer);
            return observer;
        };

        /**
         * Runs the init() method of the fieldType, if it is defined.
         **/
        var initElement = function(el) {
            if (initialized.has(el)) {
                return;
            }
            initialized.set(el, true);
            var selectors = Object.keys(self.fieldTypes);
            for (var i=selectors.length-1; i>=0; i--) {
                if (self.fieldTypes[selectors[i]].init && el.matches(selectors[i])) {
                    self.fieldTypes[selectors[i]].init.call(el, self);
                    return;
                }
            }
        };

        var self = this;
        if (el instanceof HTMLElement) {
            if (!force && el.dataset.simplyBound) {
                return;
            }
            var jsonPath = getPath(el, this.config.attribute);
            if (illegalNesting(el)) {
                el.dataset.simplyBound = 'Error: nested binding';
                console.error('Error: found nested data-binding element:',el);
                return;
            }
            attachElement(jsonPath);
            if (this.config.twoway) {
                addMutationObserver(jsonPath);
            }
        } else {
            [].forEach.call(el, function(element) {
                self.attach(element, model, force);
            });
        }
    };

    Binding.prototype.pauseObservers = function() {
        observersPaused++;
    };

    Binding.prototype.resumeObservers = function() {
        observersPaused--;
    };

    simply.bind = function(config, force) {
        return new Binding(config, force);
    };

    return simply;
})(this.simply || {}, this);this.simply = (function(simply, global) {
    simply.app = function(options) {
        if (!options) {
            options = {};
        }
        if (!options.container) {
            console.warn('No simply.app application container element specified, using document.body.');
        }
        
        function simplyApp(options) {
            if (!options) {
                options = {};
            }
            if ( options.routes ) {
                simply.route.load(options.routes);
                simply.route.handleEvents();
                global.setTimeout(function() {
                    simply.route.match(global.location.pathname+global.location.hash);
                },1);
            }
            this.container = options.container  || document.body;
            this.actions   = simply.action ? simply.action(this, options.actions) : false;
            this.commands  = simply.command ? simply.command(this, options.commands) : false;
            this.resize    = simply.resize ? simply.resize(this, options.resize) : false;
            this.view      = simply.view ? simply.view(this, options.view) : false;
            if (!(global.editor && global.editor.field) && simply.bind) {
                // skip simplyview databinding if SimplyEdit is loaded
                options.bind = simply.render(options.bind || {});
                options.bind.model = this.view;
                options.bind.container = this.container;
                this.bind = options.bindings = simply.bind(options.bind);
            }
        }

        simplyApp.prototype.get = function(id) {
            return this.container.querySelector('[data-simply-id='+id+']') || document.getElementById(id);
        };

        var app = new simplyApp(options);

        return app;
    };

    return simply;
})(this.simply || {}, this);
this.simply = (function(simply, global) {

    var listeners = {};

    simply.activate = {
        addListener: function(name, callback) {
            if (!listeners[name]) {
                listeners[name] = [];
            }
            listeners[name].push(callback);
            initialCall(name);
        },
        removeListener: function(name, callback) {
            if (!listeners[name]) {
                return false;
            }
            listeners[name] = listeners[name].filter(function(listener) {
                return listener!=callback;
            });
        }
    };

    var initialCall = function(name) {
        var nodes = document.querySelectorAll('[data-simply-activate="'+name+'"]');
        if (nodes) {
            [].forEach.call(nodes, function(node) {
                callListeners(node);
            });
        }
    };

    var callListeners = function(node) {
        if (node && node.dataset.simplyActivate 
            && listeners[node.dataset.simplyActivate]
        ) {
            listeners[node.dataset.simplyActivate].forEach(function(callback) {
                callback.call(node);
            });
        }
    };

    var handleChanges = function(changes) {
        var activateNodes = [];
        for (var change of changes) {
            if (change.type=='childList') {
                [].forEach.call(change.addedNodes, function(node) {
                    if (node.querySelectorAll) {
                        var toActivate = [].slice.call(node.querySelectorAll('[data-simply-activate]'));
                        if (node.matches('[data-simply-activate]')) {
                            toActivate.push(node);
                        }
                        activateNodes = activateNodes.concat(toActivate);
                    }
                });
            }
        }
        if (activateNodes.length) {
            activateNodes.forEach(function(node) {
                callListeners(node);
            });
        }
    };

    var observer = new MutationObserver(handleChanges);
    observer.observe(document, {
        subtree: true,
        childList: true
    });

    return simply;
})(this.simply || {}, this);
this.simply = (function(simply, global) {
    var defaultActions = {
        'simply-hide': function(el) {
            el.classList.remove('simply-visible');
            return Promise.resolve();
        },
        'simply-show': function(el) {
            el.classList.add('simply-visible');
            return Promise.resolve();
        },
        'simply-select': function(el,group,target,targetGroup) {
            if (group) {
                this.call('simply-deselect', this.app.container.querySelectorAll('[data-simply-group='+group+']'));
            }
            el.classList.add('simply-selected');
            if (target) {
                this.call('simply-select',target,targetGroup);
            }
            return Promise.resolve();
        },
        'simply-toggle-select': function(el,group,target,targetGroup) {
            if (!el.classList.contains('simply-selected')) {
                this.call('simply-select',el,group,target,targetGroup);
            } else {
                this.call('simply-deselect',el,target);
            }
            return Promise.resolve();
        },
        'simply-toggle-class': function(el,className,target) {
            if (!target) {
                target = el;
            }
            return Promise.resolve(target.classList.toggle(className));
        },
        'simply-deselect': function(el,target) {
            if ( typeof el.length=='number' && typeof el.item=='function') {
                el = Array.prototype.slice.call(el);
            }
            if ( Array.isArray(el) ) {
                for (var i=0,l=el.length; i<l; i++) {
                    this.call('simply-deselect',el[i],target);
                    target = null;
                }
            } else {
                el.classList.remove('simply-selected');
                if (target) {
                    this.call('simply-deselect',target);
                }
            }
            return Promise.resolve();
        },
        'simply-fullscreen': function(target) {
            var methods = {
                'requestFullscreen':{exit:'exitFullscreen',event:'fullscreenchange',el:'fullscreenElement'},
                'webkitRequestFullScreen':{exit:'webkitCancelFullScreen',event:'webkitfullscreenchange',el:'webkitFullscreenElement'},
                'msRequestFullscreen':{exit:'msExitFullscreen',event:'MSFullscreenChange',el:'msFullscreenElement'},
                'mozRequestFullScreen':{exit:'mozCancelFullScreen',event:'mozfullscreenchange',el:'mozFullScreenElement'}
            };
            for ( var i in methods ) {
                if ( typeof document.documentElement[i] != 'undefined' ) {
                    var requestMethod = i;
                    var cancelMethod = methods[i].exit;
                    var event = methods[i].event;
                    var element = methods[i].el;
                    break;
                }
            }
            if ( !requestMethod ) {
                return;
            }
            if (!target.classList.contains('simply-fullscreen')) {
                target.classList.add('simply-fullscreen');
                target[requestMethod]();
                var exit = function() {
                    if ( !document[element] ) {
                        target.classList.remove('simply-fullscreen');
                        document.removeEventListener(event,exit);
                    }
                };
                document.addEventListener(event,exit);
            } else {
                target.classList.remove('simply-fullscreen');
                document[cancelMethod]();
            }
            return Promise.resolve();
        }
    };

    simply.action = function(app, inActions) {
        var actions = Object.create(defaultActions);
        for ( var i in inActions ) {
            actions[i] = inActions[i];
        }

        actions.app = app;
        actions.call = function(name) {
            var params = Array.prototype.slice.call(arguments);
            params.shift();
            return this[name].apply(this, params);
        };
        return actions;
    };

    return simply;
    
})(this.simply || {}, this);
