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

(function (global) {
    'use strict';

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

    var observe = function(model, path, callback, options) {
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

    if (typeof module !== 'undefined' && typeof module.exports !== 'undefined') {
        module.exports = observe;
    } else {
        if (!global.simply) {
            global.simply = {};
        }
        global.simply.observe = observe;
    }
})(this);(function(global) {
    'use strict';

    var render = function(options) {
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

    if (typeof module !== 'undefined' && typeof module.exports !== 'undefined') {
        module.exports = render;
    } else {
        if (!global.simply) {
            global.simply = {};
        }
        global.simply.render = render;
    }
})(this);
(function(global) {
    'us strict';

    var path = {
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

    if (typeof module !== 'undefined' && typeof module.exports !== 'undefined') {
        module.exports = path;
    } else {
        if (!global.simply) {
            global.simply = {};
        }
        global.simply.path = path;
    }
})(this);
(function(global) {
    'use strict';

    var routeInfo = [];
    var listeners = {
        goto: {},
        match: {},
        call: {},
        finish: {}
    };

    function getRegexpFromRoute(route) {
        return new RegExp('^'+route.replace(/:\w+/g, '([^/]+)').replace(/:\*/, '(.*)'));
    }

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
                match:  getRegexpFromRoute(path),
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
            && link.hostname==global.location.hostname 
            && !link.link
            && !link.dataset.simplyCommand
        ) {
            let path = getPath(link.pathname+link.hash);
            if ( !route.has(path) ) {
                path = getPath(link.pathname);
            }
            if ( route.has(path) ) {
                let params = runListeners('goto', { path: path});
                if (params.path) {
                    route.goto(params.path);
                }
                evt.preventDefault();
                return false;
            }
        }
    };

    var options = {
        root: '/'
    };

    var getPath = function(path) {
        if (path.substring(0,options.root.length)==options.root
            ||
            ( options.root[options.root.length-1]=='/' 
                && path.length==(options.root.length-1)
                && path == options.root.substring(0,path.length)
            )
        ) {
            path = path.substring(options.root.length);
        }
        if (path[0]!='/' && path[0]!='#') {
            path = '/'+path;
        }
        return path;
    };

    var getUrl = function(path) {
        path = getPath(path);
        if (options.root[options.root.length-1]==='/' && path[0]==='/') {
            path = path.substring(1);
        }
        return options.root + path;
    };

    function runListeners(action, params) {
        if (!Object.keys(listeners[action])) {
            return;
        }
        Object.keys(listeners[action]).forEach(function(route) {
            var routeRe = getRegexpFromRoute(route);
            if (routeRe.exec(params.path)) {
                var result;
                listeners[action][route].forEach(function(callback) {
                    result = callback.call(global, params);
                    if (result) {
                        params = result;
                    }
                });
            }
        });
        return params;
    }

    var route = {
        handleEvents: function() {
            global.addEventListener('popstate', function() {
                if (route.match(getPath(document.location.pathname + document.location.hash)) === false) {
                    route.match(getPath(document.location.pathname));
                }
            });
            global.document.addEventListener('click', linkHandler);
        },
        load: function(routes) {
            parseRoutes(routes);
        },
        clear: function() {
            routeInfo = [];
            listeners = {
                match: {},
                call: {},
                finish: {}
            };
        },
        match: function(path, options) {
            var args = {
                path: path,
                options: options
            };
            args = runListeners('match',args);
            path = args.path ? args.path : path;

            var matches;
            if (!path) {
                if (route.match(document.location.pathname+document.location.hash)) {
                    return true;
                } else {
                    return route.match(document.location.pathname);
                }
            }
            path = getPath(path);
            for ( var i=0; i<routeInfo.length; i++) {
                matches = routeInfo[i].match.exec(path);
                if (!matches || !matches.length) {
                    if (path && path[path.length-1]!='/') {
                        matches = routeInfo[i].match.exec(path+'/');
                        if (matches) {
                            path+='/';
                            history.replaceState({}, '', getUrl(path));
                        }
                    }
                }
                if (matches && matches.length) {
                    var params = {};
                    routeInfo[i].params.forEach(function(key, i) {
                        if (key=='*') {
                            key = 'remainder';
                        }
                        params[key] = matches[i+1];
                    });
                    Object.assign(params, options);
                    args.route = route;
                    args.params = params;
                    args = runListeners('call', args);
                    params = args.params ? args.params : params;
                    args.result = routeInfo[i].action.call(route, params);
                    runListeners('finish', args);
                    return args.result;
                }
            }
            return false;
        },
        goto: function(path) {
            history.pushState({},'',getUrl(path));
            return route.match(path);
        },
        has: function(path) {
            path = getPath(path);
            for ( var i=0; i<routeInfo.length; i++) {
                var matches = routeInfo[i].match.exec(path);
                if (matches && matches.length) {
                    return true;
                }
            }
            return false;
        },
        addListener: function(action, route, callback) {
            if (['goto','match','call','finish'].indexOf(action)==-1) {
                throw new Error('Unknown action '+action);
            }
            if (!listeners[action][route]) {
                listeners[action][route] = [];
            }
            listeners[action][route].push(callback);
        },
        removeListener: function(action, route, callback) {
            if (['match','call','finish'].indexOf(action)==-1) {
                throw new Error('Unknown action '+action);
            }
            if (!listeners[action][route]) {
                return;
            }
            listeners[action][route] = listeners[action][route].filter(function(listener) {
                return listener != callback;
            });
        },
        init: function(params) {
            if (params.root) {
                options.root = params.root;
            }
        }
    };

    if (typeof module !== 'undefined' && typeof module.exports !== 'undefined') {
        module.exports = route;
    } else {
        if (!global.simply) {
            global.simply = {};
        }
        global.simply.route = route;
    }
})(this);
(function(global) {
    'use strict';

    var listeners = {};

     var activate = {
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

    if (typeof module !== 'undefined' && typeof module.exports !== 'undefined') {
        module.exports = activate;
    } else {
        if (!global.simply) {
            global.simply = {};
        }
        global.simply.activate = activate;
    }
})(this);
(function(global) {
    'use strict';

    var knownCollections = {};
    
    var collect = {
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
    
    global.addEventListener('change', function(evt) {
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

    if (typeof module !== 'undefined' && typeof module.exports !== 'undefined') {
        module.exports = collect;
    } else {
        if (!global.simply) {
            global.simply = {};
        }
        global.simply.collect = collect;
    }

})(this);
(function(global) {
    'use strict';

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
                if (el.tagName==='SELECT' && el.multiple) {
                    var values = [], opt;
                    for (var i=0,l=el.options.length;i<l;i++) {
                        var opt = el.options[i];
                        if (opt.selected) {
                            values.push(opt.value);
                        }
                    }
                    return values;
                }
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
                    if (data[el.name] && !Array.isArray(data[el.name])) {
                        data[el.name] = [data[el.name]];
                    }
                    if (Array.isArray(data[el.name])) {
                        data[el.name].push(el.value);
                    } else {
                        data[el.name] = el.value;
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

    var command = function(app, inCommands) {

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

    if (typeof module !== 'undefined' && typeof module.exports !== 'undefined') {
        module.exports = command;
    } else {
        if (!global.simply) {
            global.simply = {};
        }
        global.simply.command = command;
    }
 
})(this);
(function(global) {
    'use strict';

    function keyboard(app, config) {
        var keys = config;

        if (!app) {
            app = {};
        }
        if (!app.container) {
            app.container = document.body;
        }
        app.container.addEventListener('keydown', (e) => {
            if (e.isComposing || e.keyCode === 229) {
                return;
            }
            if (e.defaultPrevented) {
                return;
            }
            if (!e.target) {
                return;
            }

            let selectedKeyboard = 'default';
            if (e.target.closest('[data-simply-keyboard]')) {
                selectedKeyboard = e.target.closest('[data-simply-keyboard]').dataset.simplyKeyboard;
            }
            let key = '';
            if (e.ctrlKey && e.keyCode!=17) {
                key+='Control+';
            }
            if (e.metaKey && e.keyCode!=224) {
                key+='Meta+';
            }
            if (e.altKey && e.keyCode!=18) {
                key+='Alt+';
            }
            if (e.shiftKey && e.keyCode!=16) {
                key+='Shift+';
            }
            key+=e.key;

            if (keys[selectedKeyboard] && keys[selectedKeyboard][key]) {
                let keyboard = keys[selectedKeyboard]
                keyboard.app = app;
                keyboard[key].call(keyboard,e);
            }
        });

        return keys;
    }


    if (typeof module !== 'undefined' && typeof module.exports !== 'undefined') {
        module.exports = keyboard;
    } else {
        if (!global.simply) {
            global.simply = {};
        }
        global.simply.keyboard = keyboard;
    }
})(this);
(function(global) {
	'use strict';

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

     var action = function(app, inActions) {
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

    if (typeof module !== 'undefined' && typeof module.exports !== 'undefined') {
        module.exports = action;
    } else {
        if (!global.simply) {
            global.simply = {};
        }
        global.simply.action = action;
    }

})(this);
(function(global) {
    'use strict';

    var resize = function(app, config) {
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
                        match = sizes.pop(); // skip to next match to remove these
                    }
                    break;
                }
                match = sizes.pop();
            }
            while (match) {
                if ( app.container.classList.contains(match)) {
                    app.container.classList.remove(match);
                }
                match = sizes.pop();
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

    if (typeof module !== 'undefined' && typeof module.exports !== 'undefined') {
        module.exports = resize;
    } else {
        if (!global.simply) {
            global.simply = {};
        }
        global.simply.resize = resize;
    }
})(this);(function (global) {
    'use strict';

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
        let url = new URL(relative, base)
        if (include.cacheBuster) {
            url.searchParams.set('cb',include.cacheBuster)
        }
        return url.href
    };

    var observer, loaded = {};
    var head = global.document.querySelector('head');
    var currentScript = global.document.currentScript;
    if (!currentScript) {
        var getScriptURL = (function() {
            var scripts = document.getElementsByTagName('script');
            var index = scripts.length - 1;
            var myScript = scripts[index];
            return function() { return myScript.src; };
        })();
        var currentScriptURL = getScriptURL();
    } else {
        var currentScriptURL = currentScript.src;
    }

    var waitForPreviousScripts = function() {
        // because of the async=false attribute, this script will run after
        // the previous scripts have been loaded and run
        // simply.include.next.js only fires the simply-next-script event
        // that triggers the Promise.resolve method
        return new Promise(function(resolve) {
            var next = global.document.createElement('script');
            next.src = rebaseHref('simply.include.next.js', currentScriptURL);
            next.async = false;
            global.document.addEventListener('simply-include-next', function() {
                head.removeChild(next);
                resolve();
            }, { once: true, passive: true});
            head.appendChild(next);
        });
    };

    var scriptLocations = [];

    var include = {
        cacheBuster: null,
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
                var clone  = global.document.createElement('script');
                attrs.forEach(function(attr) {
                    clone.setAttribute(attr, script.getAttribute(attr));
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
            var fragment = global.document.createRange().createContextualFragment(html);
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
            var scriptsFragment = global.document.createDocumentFragment();
            // FIXME: this loses the original position of the script
            // should add a placeholder so we can reinsert the clone
            var scripts = fragment.querySelectorAll('script');
            [].forEach.call(scripts, function(script) {
                var placeholder = global.document.createComment(script.src || 'inline script');
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
                    global.editor.data.apply(global.editor.currentData, global.document);
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
            var links = global.document.querySelectorAll('link[rel="simply-include"],link[rel="simply-include-once"]');
            if (links.length) {
                includeLinks(links);
            }
        });
    });

    var observe = function() {
        observer = new MutationObserver(handleChanges);
        observer.observe(global.document, {
            subtree: true,
            childList: true,
        });
    };

    observe();
    handleChanges(); // check if there are include links in the dom already

    if (typeof module !== 'undefined' && typeof module.exports !== 'undefined') {
        module.exports = include;
    } else {
        if (!global.simply) {
            global.simply = {};
        }
        global.simply.include = include;
    }


})(this);
(function(global) {
    'use strict';
    var view = function(app, view) {

        app.view = view || {};

        var load = function() {
            var data = app.view;
            var path = global.editor.data.getDataPath(app.container);
            app.view = global.editor.currentData[path];
            Object.keys(data).forEach(function(key) {
                app.view[key] = data[key];
            });
        };

        if (global.editor && global.editor.currentData) {
            load();
        } else {
            global.document.addEventListener('simply-content-loaded', function() {
                load();
            });
        }
        
        return app.view;
    };

    if (typeof module !== 'undefined' && typeof module.exports !== 'undefined') {
        module.exports = view;
    } else {
        if (!global.simply) {
            global.simply = {};
        }
        global.simply.view = view;
    }
})(this);
(function(global) {
    'use strict';

    function etag() {
        let d = '';
        while (d.length < 32) d += Math.random().toString(16).substr(2);
        const vr = ((parseInt(d.substr(16, 1), 16) & 0x3) | 0x8).toString(16);
        return `${d.substr(0, 8)}-${d.substr(8, 4)}-4${d.substr(13, 3)}-${vr}${d.substr(17, 3)}-${d.substr(20, 12)}`;
    }
    
    function ViewModel(name, data, options) {
        this.name = name;
        this.data = data || [];
        this.data.etag = etag();
        this.view = {
            options: {},
            data: [] //Array.from(this.data).slice()
        };
        this.options = options || {};
        this.plugins = {
            start: [],
            select: [],
            order: [],
            render: [],
            finish: []
        };
    }

    ViewModel.prototype.update = function(params) {
        if (!params) {
            params = {};
        }
        if (params.data) {
            // this.data is a reference to the data passed, so that any changes in it will get applied
            // to the original
            this.data = params.data;
            this.data.etag = etag()
        }
        // the view is a shallow copy of the array, so that changes in sort order and filtering
        // won't get applied to the original, but databindings on its children will still work
        this.view.data = Array.from(this.data).slice();
        this.view.data.etag = this.data.etag;
        let data = this.view.data;
        let plugins = this.plugins.start.concat(this.plugins.select, this.plugins.order, this.plugins.render, this.plugins.finish);
        plugins.forEach(plugin => {
            data = plugin.call(this, params, data);
            if (!data) {
                data = this.view.data;
            }
            this.view.data = data
        });

        if (global.editor) {
            global.editor.addDataSource(this.name,{
                load: function(el, callback) {
                    callback(self.view.data);
                }
            });
            updateDataSource(this.name);
        }
    };

    ViewModel.prototype.addPlugin = function(pipe, plugin) {
        if (typeof this.plugins[pipe] == 'undefined') {
            throw new Error('Unknown pipeline '+pipe);
        }
        this.plugins[pipe].push(plugin);
    };

    ViewModel.prototype.removePlugin = function(pipe, plugin) {
        if (typeof this.plugins[pipe] == 'undefined') {
            throw new Error('Unknown pipeline '+pipe);
        }
        this.plugins[pipe] = this.plugins[pipe].filter(function(p) {
            return p != plugin;
        });
    };

    var updateDataSource = function(name) {
        global.document.querySelectorAll('[data-simply-data="'+name+'"]').forEach(function(list) {
            global.editor.list.applyDataSource(list, name);
        });
    };

    var createSort = function(options) {
        var defaultOptions = {
            name: 'sort',
            getSort: function(params) {
                return Array.prototype.sort;
            }
        };
        options = Object.assign(defaultOptions, options || {});

        return function(params) {
            this.options[options.name] = options;
            if (params[options.name]) {
                options = Object.assign(options, params[options.name]);
            }
            this.view.data.sort(options.getSort.call(this, options));
        };
    };

    var createPaging = function(options) {
        var defaultOptions = {
            name: 'paging',
            page: 1,
            pageSize: 100,
            max: 1,
            prev: 0,
            next: 0
        };
        options = Object.assign(defaultOptions, options || {});

        return function(params) {
            this.options[options.name] = options;
            if (this.view.data) {
                options.max = Math.max(1, Math.ceil(Array.from(this.view.data).length / options.pageSize));
            } else {
                options.max = 1;
            }
            if (this.view.changed) {
                options.page = 1; // reset to page 1 when something in the view data has changed
            }
            if (params[options.name]) {
                options = Object.assign(options, params[options.name]);
            }
            options.page = Math.max(1, Math.min(options.max, options.page)); // clamp page nr
            options.prev = options.page - 1; // calculate previous page, 0 is allowed
            if (options.page<options.max) {
                options.next = options.page + 1;
            } else {
                options.next = 0; // no next page
            }

            var start = (options.page - 1) * options.pageSize;
            var end   = start + options.pageSize;

            this.view.data = this.view.data.slice(start, end);
        };
    };

    var createFilter = function(options) {
        var defaultOptions = {
            name: 'filter',
            label: 'A filter',
            getMatch: function(entry) {
                return false;
            }
        };
        options = Object.assign(defaultOptions, options || {});
        if (options.init) {
            options.init.call(this, options);
        }
        return function(params) {
            this.options[options.name] = options;
            if (params[options.name]) {
                options = Object.assign(options, params[options.name]);
            }
            var match = options.getMatch.call(this, options);
            if (match) {
                options.enabled = true;
                this.view.data = this.view.data.filter(match);
            } else if (options.enabled) {
                options.enabled = false;
            }
        }
    }

    var viewmodel = {
        create: function(name, data, options) {
            return new ViewModel(name, data, options);
        },
        createFilter: createFilter,
        createSort: createSort,
        createPaging: createPaging,
        updateDataSource: updateDataSource,
        etag
    };

    if (typeof module !== 'undefined' && typeof module.exports !== 'undefined') {
        module.exports = viewmodel;
    } else {
        if (!global.simply) {
            global.simply = {};
        }
        global.simply.viewmodel = viewmodel;
    }

})(this);(function(global) {
    'use strict';

    var api = {
        /**
         * Returns a Proxy object that translates property access to a URL in the api
         * and method calls to a fetch on that URL.
         * @param options: a list of options for fetch(), 
		 * see the 'init' parameter at https://developer.mozilla.org/en-US/docs/Web/API/WindowOrWorkerGlobalScope/fetch#parameters 
         * additionally:
         * - baseURL: (required) the endpoint of the API
         * - path: the current path in the API, is appended to the baseURL
         * - verbs: list of http verbs to allow as methods, default ['get','post']
         * - handlers.fetch: alternative fetch method
         * - handlers.result: alternative getResult method
         * - handlers.error: alternative error method
         * - user (and password): if set, a basic authentication header will be added
         * - paramsFormat: either 'formData', 'json' or 'search'. Default is search.
         * - responseFormat: test, formData, blob, json, arrayBuffer or unbuffered. Default is json.
         * @return Proxy
         */
        proxy: function(options) {
            var cache = () => {};
            cache.$options = Object.assign({}, options);
            return new Proxy( cache, getApiHandler(cache.$options) );
        },

        /**
         * Fetches the options.baseURL using the fetch api and returns a promise
         * Extra options in addition to those of global.fetch():
         * - user (and password): if set, a basic authentication header will be added
         * - paramsFormat: either 'formData', 'json' or 'search'
         * By default params, if set, will be added to the baseURL as searchParams
         * @param method one of the http verbs, e.g. get, post, etc.
         * @param options the options for fetch(), with some additions
         * @param params the parameters to send with the request, as javascript/json data
         * @return Promise
         */
        fetch: function(method, params, options) {
            if (!options.url) {
                if (!options.baseURL) {
                    throw new Error('No url or baseURL in options object');
                }
                while (options.baseURL[options.baseURL.length-1]=='/') {
                    options.baseURL = options.baseURL.substr(0, options.baseURL.length-1);
                }
                var url = new URL(options.baseURL+options.path);
            } else {
                var url = options.url;
            }
            var fetchOptions = Object.assign({}, options);
            if (!fetchOptions.headers) {
                fetchOptions.headers = {};
            }
            if (params) {
                if (method=='GET') {
                    var paramsFormat = 'search';
                } else {
                    var paramsFormat = options.paramsFormat;
                }
                switch(paramsFormat) {
                    case 'formData':
                        var formData = new FormData();
                        for (const name in params) {
                            formData.append(name, params[name]);
                        }
                        if (!fetchOptions.headers['Content-Type']) {
                            fetchOptions.headers['Content-Type'] = 'application/x-www-form-urlencoded';
                        }
                        break;
                    case 'json':
                        var formData = JSON.stringify(params);
                        if (!fetchOptions.headers['Content-Type']) {
                            fetchOptions.headers['Content-Type'] = 'application/json';
                        }
                        break;
                    case 'search':
                        var searchParams = url.searchParams; //new URLSearchParams(url.search.slice(1));
                        for (const name in params) {
                            searchParams.set(name, params[name]);
                        }
                        url.search = searchParams.toString();
                        break;
                    default:
                        throw Error('Unknown options.paramsFormat '+options.paramsFormat+'. Select one of formData, json or search.');
                        break;
                }
            }
            if (formData) {
                fetchOptions.body = formData
            }
            if (options.user) {
                fetchOptions.headers['Authorization'] = 'Basic '+btoa(options.user+':'+options.password);
            }
            fetchOptions.method = method.toUpperCase();
            var fetchURL = url.toString()
            return fetch(fetchURL, fetchOptions);
        },
        /**
         * Creates a function to call one or more graphql queries
         */
        graphqlQuery: function(url, query, options) {
            options = Object.assign({ paramsFormat: 'json', url: url, responseFormat: 'json' }, options);
            return function(params, operationName) {
                let postParams = {
                    query: query
                };
                if (operationName) {
                    postParams.operationName = operationName;
                }
                postParams.variables = params || {};
                return simply.api.fetch('POST', postParams, options )
                .then(function(response) {
                    return simply.api.getResult(response, options);
                });
            }  
        },
        /**
         * Handles the response and returns a Promise with the response data as specified
         * @param response Response
         * @param options
         * - responseFormat: one of 'text', 'formData', 'blob', 'arrayBuffer', 'unbuffered' or 'json'.
         * The default is json.
         */
        getResult: function(response, options) {
            if (response.ok) {
                switch(options.responseFormat) {
                    case 'text':
                        return response.text();
                    break;
                    case 'formData':
                        return response.formData();
                    break;
                    case 'blob':
                        return response.blob();
                    break;
                    case 'arrayBuffer':
                        return response.arrayBuffer();
                    break;
                    case 'unbuffered':
                        return response.body;
                    break;
                    case 'json':
                    default:
                        return response.json();
                    break;
                }
            } else {
                throw {
                    status: response.status,
                    message: response.statusText,
                    response: response
                }
            }
        },
        logError: function(error, options) {
            console.error(error.status, error.message);
        }
    }

    var defaultOptions = {
        path: '',
        responseFormat: 'json',
        paramsFormat: 'search',
        verbs: ['get','post'],
        handlers: {
            fetch:  api.fetch,
            result: api.getResult,
            error:  api.logError
        }
    };

    function cd(path, name) {
        name = name.replace(/\//g,'');
        if (!path.length || path[path.length-1]!=='/') {
            path+='/';
        }
        return path+encodeURIComponent(name);
    }

    function fetchChain(prop, params) {
        var options = this;
        return this.handlers.fetch
            .call(this, prop, params, options)
            .then(function(res) {
                return options.handlers.result.call(options, res, options);
            })
            .catch(function(error) {
                return options.handlers.error.call(options, error, options);
            });
    }

    function getApiHandler(options) {
        options.handlers = Object.assign({}, defaultOptions.handlers, options.handlers);
        options = Object.assign({}, defaultOptions, options);

        return {
            get: function(cache, prop) {
                if (!cache[prop]) {
                    if (options.verbs.indexOf(prop)!=-1) { 
                        cache[prop] = function(params) {
                            return fetchChain.call(options, prop, params);
                        }
                    } else {
                        cache[prop] = api.proxy(Object.assign({}, options, {
                            path: cd(options.path, prop)
                        }));
                    }
                }
                return cache[prop];
            },
            apply: function(cache, thisArg, params) {
                return fetchChain.call(options, 'get', params[0] ? params[0] : null)
            }
        }
    }


    if (typeof module !== 'undefined' && typeof module.exports !== 'undefined') {
        module.exports = api;
    } else {
        if (!global.simply) {
            global.simply = {};
        }
        global.simply.api = api;
    }

})(this);
(function(global) {
    'use strict';

    var app = function(options) {
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
                if (options.routeEvents) {
                    Object.keys(options.routeEvents).forEach(function(action) {
                        Object.keys(options.routeEvents[action]).forEach(function(route) {
                            options.routeEvents[action][route].forEach(function(callback) {
                                simply.route.addListener(action, route, callback);
                            });
                        });
                    });
                }
                simply.route.handleEvents();
                global.setTimeout(function() {
                    simply.route.match(global.location.pathname+global.location.hash);
                });
            }
            this.container = options.container || document.body;
            this.keyboard  = simply.keyboard ? simply.keyboard(this, options.keyboard || {}) : false;
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

        return new simplyApp(options);
    };

    if (typeof module !== 'undefined' && typeof module.exports !== 'undefined') {
        module.exports = app;
    } else {
        if (!global.simply) {
            global.simply = {};
        }
        global.simply.app = app;
    }

})(this);