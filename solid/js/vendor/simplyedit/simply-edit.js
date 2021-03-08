/*
	Simply edit the Web

	Written by Yvo Brevoort
	Copyright Muze 2015-2020, all rights reserved.
*/
(function() {
	if (window.editor) {
		return;
	}

	var getScriptEl = function() {
		var scriptEl = document.querySelector("[src$='simply-edit.js'][data-api-key]");
		if (!scriptEl) {
			scriptEl = document.querySelector("[src$='simply-edit.js']");
		}
		if (!scriptEl) {
			scriptEl = document.querySelector("[data-api-key]");
		}
		if (!scriptEl) {
			scriptEl = document.querySelector("[src*='simply-edit.js']");
		}
		return scriptEl;
	};

	var scriptEl = getScriptEl();
	var apiKey = scriptEl.getAttribute("data-api-key") ? scriptEl.getAttribute("data-api-key") : "";

	var getKeylessBaseURL  = function(url) {
		var scriptURL = document.createElement('a');
		scriptURL.href = url;
		scriptURL.pathname = scriptURL.pathname.replace('simply-edit.js', '').replace(/\/js\/$/, '/');
		return scriptURL.href;
	};

	var getBaseURL = function(url) {
		var scriptURL = document.createElement('a');
		scriptURL.href = url;
		scriptURL.pathname = scriptURL.pathname.replace('simply-edit.js', '').replace(/\/js\/$/, '/');
		if (apiKey !== "" && apiKey !== "muze" && apiKey !== "github") {
			scriptURL.pathname = scriptURL.pathname + apiKey + "/";
		}
		return scriptURL.href;
	};

	var editor = {
		version: '@version',
		apiKey : apiKey,
		baseURL : getBaseURL(scriptEl.src),
		baseURLClean : getKeylessBaseURL(scriptEl.src),
		bindingParents : [],
		transformers: {},
		data : {
			getDataPath : function(field) {
				var parent = field;
				while (parent && parent.parentNode) {
					var parentPath = parent.getAttribute("data-simply-path");
					if (parentPath) {
						if (parentPath.indexOf("/") !== 0) { // Resolve as relative path if it doesn't start with a slash; allows the use of ../
							var resolver = document.createElement("A");
							var basePath = location.pathname.replace(/(.*)\/.*?$/, "$1/");
							resolver.href = basePath + parentPath;
							if (resolver.pathname.indexOf("./") == -1) {
								// IE11 doesn't add a leading slash;
								if (resolver.pathname.indexOf("/") !== 0) {
									return "/" + resolver.pathname;
								} else {
									return resolver.pathname;
								}
							} else {
								var origin = document.location.origin;
								if (!origin) {
									origin = document.location.protocol + "//" + document.location.host; // IE9 doesn't have document.location.origin
								}
								return resolver.href.replace(origin, ""); // IE11 doesn't resolve ../ or ./ in the path
							}
						}
						return parentPath;
					}
					parent = parent.parentNode;
				}
				if (parent && parent.dataSimplyPath) {
					return parent.dataSimplyPath;
				}
				if (field && field.storedPath && !field.offsetParent) {
					return field.storedPath;
				}
				return location.pathname;
			},
			apply : function(data, target) {
				if (typeof data === "undefined") {
					data = {};
				}

				// data = JSON.parse(JSON.stringify(data)); // clone data to prevent reference issues;

				if (typeof editor.data.originalBody === "undefined" && document.body) {
					editor.data.originalBody = document.body.cloneNode(true);
				}

				editor.responsiveImages.init(target); // FIXME: should this be more defensive and skip images within fields/lists?

				var dataFields;
				if (target.nodeType == document.ELEMENT_NODE && target.getAttribute("data-simply-field")) {
					dataFields = [target];
					if (target.getAttribute("data-simply-content") === 'fixed') { // special case - if the target field has content fixed, we need to handle its children as well.
						var extraFields = target.querySelectorAll("[data-simply-field]");
						for (var x=0; x<extraFields.length; x++) {
							dataFields.push(extraFields[x]);
						}
					}
				} else {
					dataFields = target.querySelectorAll("[data-simply-field]");
				}
				var subFields;
				if (target.nodeType == document.DOCUMENT_NODE || target.nodeType == document.DOCUMENT_FRAGMENT_NODE || !target.parentNode) {
					subFields = target.querySelectorAll("[data-simply-list] [data-simply-field], template [data-simply-field]");
				} else {
					subFields = target.querySelectorAll(":scope [data-simply-list] [data-simply-field], :scope template [data-simply-field]"); // use :scope here, otherwise it will also return items that are a part of a outside-scope-list
				}

				// prepare this as an array so we can use indexOf to check for elements;
				var subFieldsArr = [];
				for (var a=0; a<subFields.length; a++) {
					subFieldsArr.unshift(subFields[a]);
				}

				if (target == document) {
					editor.settings.databind.parentKey = '/';
				} else {
					editor.settings.databind.parentKey = editor.bindingParents.join("/") + "/";
				}

				var savedParentKey = editor.settings.databind.parentKey;
				for (var i=0; i<dataFields.length; i++) {
					editor.settings.databind.parentKey = savedParentKey;

					// Only handle datafields that are our direct descendants, list descendants will be handled by the list;
					var isSub = (subFieldsArr.indexOf(dataFields[i]) > -1);
					if (isSub) {
						continue;
					}

					var dataPath = editor.data.getDataPath(dataFields[i]);
					if (!data[dataPath]) {
						data[dataPath] = {};
					}

					editor.field.init(dataFields[i], data[dataPath], true);
				}

				editor.settings.databind.parentKey = savedParentKey;
				editor.list.initLists(data, target);
				editor.fireEvent("simply-data-applied", target);
				editor.fireEvent("simply-selectable-inserted", target);
			},
			get : function(target) {
				if (target == document && editor.currentData) {
					return editor.currentData;
				} else if (target.dataBinding) {
					return target.dataBinding.get();
				} else {
					var stashedFields = target.querySelectorAll("[data-simply-stashed]");
					for (i=0; i<stashedFields.length; i++) {
						stashedFields[i].removeAttribute("data-simply-stashed");
					}
					if (target.nodeType == document.ELEMENT_NODE) {
						target.removeAttribute("data-simply-stashed");
					}

					return editor.list.get(target);
				}
			},
			stash : function() {
				editor.fireEvent("simply-stash", document);
				var dataSources = document.querySelectorAll("[data-simply-data]");
				for (var i=0; i<dataSources.length; i++) {
					editor.list.get(dataSources[i]);
				}
				localStorage.data = editor.data.stringify(editor.currentData);
				editor.fireEvent("simply-stashed", document);
			},
			stringify : function(data) {
				var jsonData = JSON.stringify(data, null, "\t");

				// Replace characters for encoding with btoa, needed for github;
				jsonData = jsonData.replace(
					/[^\x00-\x7F]/g,
					function ( char ) {
						var hex = char.charCodeAt( 0 ).toString( 16 );
						while ( hex.length < 4 ) {
							hex = '0' + hex;
						}
						return '\\u' + hex;
					}
				);
				return jsonData;
			},
			save : function() {
				editor.storage.connect( function() {
					editor.data.stash();
					if (editor.actions['simply-beforesave']) {
						editor.actions['simply-beforesave']();
					}

					var saveCallback = function(result) {
						if (!result) {
							result = {};
						}
						result.newData = localStorage.data;
						var savedEvent = editor.fireEvent("simply-data-saved", document, result);
						editor.loadedData = result.newData;

						if (result && result.error) {
							if (editor.actions['simply-aftersave-error']) {
								editor.actions['simply-aftersave-error'](result);
							} else {
								alert("Save failed: " + result.message);
							}
						} else {
							if (editor.actions['simply-aftersave']) {
								editor.actions['simply-aftersave']();
							} else {
								alert("Saved!");
							}
						}
					};

					var executeSave = function() {
						for (var source in editor.dataSources) {
							if (editor.dataSources[source].save && typeof editor.dataSources[source].stash != 'undefined') {
								for (var i=0; i<editor.dataSources[source].stash.length; i++) {
									editor.dataSources[source].save(editor.dataSources[source].stash[i]);
								}
							}
						}

						editor.storage.save(localStorage.data, saveCallback);
					};

					if (editor.actions['simply-executesave']) {
						editor.actions['simply-executesave'](executeSave);
					} else {
						executeSave();
					}
				});
			},
			load : function() {
				editor.storage.load(function(data) {
					try {
						localStorage.data = data;
					} catch(e) {
						editor.readOnly = true;
					}

					editor.loadedData = data;
					try {
						editor.currentData = JSON.parse(data);
					} catch(e) {
						editor.currentData = {};
						console.log("Warning: Not able to parse JSON data.");
					}

					editor.data.apply(editor.currentData, document);
					editor.fireEvent("simply-content-loaded", document);

					var checkEdit = function(evt) {
						if ((evt && evt.newURL && evt.newURL.match(/#simply-edit$/) || document.location.hash == "#simply-edit" || document.location.search == "?simply-edit") && !document.body.getAttribute("data-simply-edit")) {
							editor.storage.connect(function() {
								editor.editmode.init();
								var checkHope = function() {
									if (typeof hope !== "undefined" && editor.toolbarsContainer && editor.toolbarsContainer.getElementById("simply-main-toolbar")) {
										editor.editmode.makeEditable(document);
									} else {
										window.setTimeout(checkHope, 100);
									}
								};
								checkHope();
							});
						}
					};

					if ("addEventListener" in window) {
						window.addEventListener("hashchange", checkEdit);
					}
					checkEdit();
				});
			},
			bindAsParent : function(dataParent, dataKey) {
				var parentBindingConfig = {};
				for (var i in editor.settings.databind) {
					parentBindingConfig[i] = editor.settings.databind[i];
				}
				parentBindingConfig.data = dataParent;
				parentBindingConfig.key = dataKey;
				parentBindingConfig.setter = editor.field.dataBindingSetter;
				parentBindingConfig.getter = editor.field.dataBindingGetter;
				parentDataBinding = new dataBinding(parentBindingConfig);
			}
		},
		list : {
			get : function(target) {
				var i, j;
				var data = {};
				var dataName, dataPath, dataFields, dataLists, listItems;

				var addListData = function(list) {
					if (list.getAttribute("data-simply-stashed")) {
						return;
					}
					dataName = list.getAttribute("data-simply-list");
					dataPath = editor.data.getDataPath(list);

					if (!data[dataPath]) {
						data[dataPath] = {};
					}

					var dataParent = data[dataPath];
					var dataKeys = dataName.split(".");
					dataName = dataKeys.pop();
					for (var j=0; j<dataKeys.length; j++) {
						if (!dataParent[dataKeys[j]]) {
							dataParent[dataKeys[j]] = {};
						}
						editor.data.bindAsParent(dataParent, dataKeys[j]);
						dataParent = dataParent[dataKeys[j]];
					}

					if (!dataParent[dataName]) {
						dataParent[dataName] = [];
					}

					listItems = list.querySelectorAll("[data-simply-list-item]");
					var counter = 0;
					for (j=0; j<listItems.length; j++) {
						if (listItems[j].parentNode != list) {
							continue;
						}

						if (!dataParent[dataName][counter]) {
							dataParent[dataName][counter] = {};
						}
						var subData = editor.list.get(listItems[j]);
						for (var subPath in subData) {
							if (subPath != dataPath) {
								console.log("Notice: use of data-simply-path in subitems is not permitted, translated " + subPath + " to " + dataPath);
							}
							dataParent[dataName][counter] = subData[subPath];
						}

						// dataParent[dataName][counter] = editor.data.get(listItems[j]);
						if (listItems[j].getAttribute("data-simply-template")) {
							dataParent[dataName][counter]['data-simply-template'] = listItems[j].getAttribute("data-simply-template");
						}
						counter++;
					}
					list.setAttribute("data-simply-stashed", 1);

					var dataSource = list.getAttribute("data-simply-data");
					if (dataSource) {
						if (editor.dataSources[dataSource]) {
							if (!editor.dataSources[dataSource].stash) {
								editor.dataSources[dataSource].stash = [];
							}

							editor.dataSources[dataSource].stash.push({
								list : list,
								dataPath : dataPath,
								dataName : dataName,
								data : dataParent[dataName]
							});

							if (typeof editor.dataSources[dataSource].get === "function") {
								dataParent[dataName] = editor.dataSources[dataSource].get(list);
								if (dataParent[dataName] === null) {
									dataParent[dataName] = []; // returning null will confuse the databinding;
								}
							}
						}
					}
				};

				var addData = function(field) {
					if (field.getAttribute("data-simply-stashed")) {
						return;
					}

					dataName = field.getAttribute("data-simply-field");
					dataPath = editor.data.getDataPath(field);

					if (!data[dataPath]) {
						data[dataPath] = {};
					}

					var dataParent = data[dataPath];
					var dataKeys = dataName.split(".");
					dataName = dataKeys.pop();
					for (var j=0; j<dataKeys.length; j++) {
						if (!dataParent[dataKeys[j]]) {
							dataParent[dataKeys[j]] = {};
						}
						editor.data.bindAsParent(dataParent, dataKeys[j]);
						dataParent = dataParent[dataKeys[j]];
					}

					dataParent[dataName] = editor.field.get(field);
					field.setAttribute("data-simply-stashed", 1);
				};

				if (target.nodeType == document.ELEMENT_NODE && target.getAttribute("data-simply-list")) {
					addListData(target);
				}

				dataLists = target.querySelectorAll("[data-simply-list]");
				for (i=0; i<dataLists.length; i++) {
					addListData(dataLists[i]);
				}

				dataFields = target.querySelectorAll("[data-simply-field]");
				for (i=0; i<dataFields.length; i++) {
					addData(dataFields[i]);
				}
				if (target.nodeType == document.ELEMENT_NODE && target.getAttribute("data-simply-field")) {
					addData(target);
				}

				if (target.nodeType == document.ELEMENT_NODE && target.getAttribute("data-simply-list-item")) {
					if (target.getAttribute("data-simply-template")) {
						dataPath = editor.data.getDataPath(target);
						if (!data[dataPath]) {
							data[dataPath] = {};
						}
						data[dataPath]['data-simply-template'] = target.getAttribute("data-simply-template");
					}
				}

				// timeout so we do this cleanup after all is done;
				window.setTimeout(function() {
					var stashedFields = target.querySelectorAll("[data-simply-stashed]");
					for (i=0; i<stashedFields.length; i++) {
						stashedFields[i].removeAttribute("data-simply-stashed");
					}
				});
				return data;
			},
			keyDownHandler : function(evt) {
				if(evt.ctrlKey && evt.altKey && evt.keyCode == 65) { // ctrl-alt-A
					if (typeof editor.plugins.list.add !== "undefined") {
						editor.plugins.list.add(this);
						evt.preventDefault();
					}
				}
			},
			applyDataSource : function (list, dataSource, listData) {
				if (editor.dataSources[dataSource]) {
					if (editor.dataSources[dataSource].applyOnce && list.dataSourceApplied) {
						return;
					}
					if (list.dataSourceTimer) { // just in case we already have a timer running, don't do things twice;
						window.clearTimeout(list.dataSourceTimer);
					}
					if (list.dataBinding) {
						list.dataBinding.pauseListeners(list);
					}
					if (typeof editor.dataSources[dataSource].set === "function") {
						editor.dataSources[dataSource].set(list, listData);
					}
					if (typeof editor.dataSources[dataSource].load === "function") {
						editor.dataSources[dataSource].load(list, function(result) {
							editor.list.set(list, result);
							editor.responsiveImages.init(list);
							if (typeof hope !== "undefined") {
								editor.editmode.makeEditable(list);
							}
						});
					} else if (editor.dataSources[dataSource].load) {
						editor.list.set(list, editor.dataSources[dataSource].load);
						if (typeof hope !== "undefined") {
							editor.editmode.makeEditable(list);
						}
					}
					// set again, in case we wanted to set data using the result of the load;
					if (typeof editor.dataSources[dataSource].set === "function") {
						editor.dataSources[dataSource].set(list, listData);
					}
					if (list.dataBinding) {
						list.dataBinding.resumeListeners(list);
					}
					list.dataSourceApplied = true;
				} else {
					list.dataSourceTimer = window.setTimeout(function() {editor.list.applyDataSource(list, dataSource, listData);}, 500);
				}
			},
			dataBindingGetter : function() {
				if (!this.getAttribute("data-simply-list") && this.getAttribute("data-simply-field")) {
					return editor.field.dataBindingGetter.call(this);
				}
				var dataName = this.getAttribute("data-simply-list");
				var dataPath = editor.data.getDataPath(this);
				var stashedFields = this.querySelectorAll("[data-simply-stashed]");
				for (i=0; i<stashedFields.length; i++) {
					stashedFields[i].removeAttribute("data-simply-stashed");
				}
				this.removeAttribute("data-simply-stashed");
				var data = editor.list.get(this);

				var dataParent = data[dataPath];
				var dataKeys = dataName.split(".");
				dataName = dataKeys.pop();
				for (var j=0; j<dataKeys.length; j++) {
					if (!dataParent[dataKeys[j]]) {
						dataParent[dataKeys[j]] = {};
					}
					editor.data.bindAsParent(dataParent, dataKeys[j]);
					dataParent = dataParent[dataKeys[j]];
				}

				return dataParent[dataName];
			},
			dataBindingSetter : function(value) {
				if (!this.getAttribute("data-simply-list") && this.getAttribute("data-simply-field")) {
					return editor.field.dataBindingSetter.call(this, value);
				}

				var savedBindingParents = editor.bindingParents;
				if (this.dataBinding) {
					editor.bindingParents = this.dataBinding.parentKey.replace(/\/$/,'').split("/");
				}

				if (this.getAttribute('data-simply-data')) {
					editor.list.applyDataSource(this, this.getAttribute('data-simply-data'), value);
				} else {
					editor.list.set(this, value);
				}
				editor.responsiveImages.init(this);

				editor.bindingParents = savedBindingParents;
				if (document.body.getAttribute("data-simply-edit")) {
					var list = this;
					editor.editmode.makeEditable(list);
				}
			},
			initList : function(data, list) {
				editor.list.parseTemplates(list);
				var dataPath = editor.data.getDataPath(list);

				if (!data[dataPath]) {
					data[dataPath] = {};
				}

				var savedParentKey = editor.settings.databind.parentKey;

				if (list.getAttribute("data-simply-data")) {
					editor.list.init(list, data[dataPath], false);
				} else {
					editor.list.init(list, data[dataPath], true);
				}

				editor.settings.databind.parentKey = savedParentKey;
			},
			initLists : function(data, target) {
				var dataLists = target.querySelectorAll("[data-simply-list]");
				var subLists;
				if (target.nodeType == document.DOCUMENT_NODE || target.nodeType == document.DOCUMENT_FRAGMENT_NODE || !target.parentNode) {
					subLists = target.querySelectorAll("template [data-simply-list], [data-simply-list] [data-simply-list], [data-simply-field]:not([data-simply-content='attributes']):not([data-simply-content='fixed']) [data-simply-list]");
				} else {
					subLists = target.querySelectorAll(":scope template [data-simply-list], :scope [data-simply-list] [data-simply-list], :scope [data-simply-field]:not([data-simply-content='attributes']):not([data-simply-content='fixed']) [data-simply-list]"); // use :scope here, otherwise it will also return items that are a part of a outside-scope-list
				}
				var subListsArr = [];
				for (var a=0; a<subLists.length; a++) {
					subListsArr.unshift(subLists[a]);
				}

				for (var i=0; i<dataLists.length; i++) {
					var isSub = (subListsArr.indexOf(dataLists[i]) > -1);
					if (isSub) {
						continue;
					}

					editor.list.initList(data, dataLists[i]);
				}
				if (target.nodeType == document.ELEMENT_NODE && target.getAttribute("data-simply-list")) {
					editor.list.initList(data, target);
				}
			},
			fixFirstElementChild : function(clone) {
				if (!("firstElementChild" in clone)) {
					for (var l=0; l<clone.childNodes.length; l++) {
						if (clone.childNodes[l].nodeType == document.ELEMENT_NODE) {
							clone.firstElementChild = clone.childNodes[l];
						}
					}
				}
			},
			parseTemplates : function(list) {
				if (typeof list.templates !== "undefined") {
					return; // we already parsed the templates for this list;
				}

				var dataName = list.getAttribute("data-simply-list");
				var dataPath = editor.data.getDataPath(list);

				list.innerHTML = list.innerHTML; // reset innerHTML to make sure templates are recognized;
				var templates = list.querySelectorAll(":scope > template, :scope > *[data-simply-template]");

				if (templates.length === 0) {
					console.log("Warning: no list templates found for " + dataName);
				}

				if (typeof list.templates === "undefined") {
					list.templates = {};
				}
				if (typeof list.templateIcons === "undefined") {
					list.templateIcons = {};
				}
				for (var t=0; t<templates.length; t++) {
					var templateName = templates[t].getAttribute("data-simply-template");
					if (!templateName) {
						templateName = t;
					}

					// Allow the 'rel' attribute to point to the contents of another (global) template;
					var sourceTemplate = templates[t].getAttribute("rel");
					if (sourceTemplate) {
						if (document.getElementById(sourceTemplate)) {
							list.templates[templateName] = document.getElementById(sourceTemplate);
						} else if (editor.toolbarsContainer && editor.toolbarsContainer.getElementById(sourceTemplate)) {
							list.templates[templateName] = editor.toolbarsContainer.getElementById(sourceTemplate);
						} else {
							console.log("Warning: could not find a template with ID '" + sourceTemplate + "'");
						}
					} else if (templates[t].tagName.toLowerCase() !== "template") {
						/* If it is not a template tag, the element itself should be used as a template, instead of the contents. */
						var templateNode = document.createElement("div");
						templateNode.setAttribute("data-simply-template", templates[t].getAttribute("data-simply-template"));
						templates[t].removeAttribute("data-simply-template");
						templateNode.appendChild(templates[t].cloneNode(true));
						list.templates[templateName] = templateNode;
					} else {
						list.templates[templateName] = templates[t];
					}

					if (typeof list.templates[templateName] === "undefined") {
						console.log("Warning: template '" + templateName + "' was not defined.");
						return;
					}

					if (!("content" in list.templates[templateName])) {
						var fragment = document.createDocumentFragment();
						var fragmentNode = document.createElement("FRAGMENT");

						content  = list.templates[templateName].children;
						for (j = 0; j < content.length; j++) {
							fragmentNode.appendChild(content[j].cloneNode(true));
							fragment.appendChild(content[j]);
						}
						list.templates[templateName].content = fragment;
						list.templates[templateName].contentNode = fragmentNode;
					}
					var templateIcon = templates[t].getAttribute("data-simply-template-icon");
					if (templateIcon) {
						list.templateIcons[templateName] = templateIcon;
					}
					if (!list.defaultTemplate) {
						list.defaultTemplate = templateName;
					}
				}
				for (t=0; t<templates.length; t++) {
					templates[t].parentNode.removeChild(templates[t]);
				}
			},
			init : function(list, dataParent, useDataBinding) {
				editor.list.parseTemplates(list);
				var dataName = list.getAttribute("data-simply-list");

				var dataKeys = dataName.split(".");
				dataName = dataKeys.pop();
				for (var j=0; j<dataKeys.length; j++) {
					if (!dataParent[dataKeys[j]]) {
						dataParent[dataKeys[j]] = {};
					}
					editor.data.bindAsParent(dataParent, dataKeys[j]);
					dataParent = dataParent[dataKeys[j]];
					editor.settings.databind.parentKey += dataKeys[j] + "/";
				}

				if (!dataParent[dataName]) {
					dataParent[dataName] = [];
				}
				if (list.dataBinding && list.dataBinding.mode == "field") {
					useDataBinding = false; // this list is already bound as a field, skip dataBinding
				}
				if (list.getAttribute("data-simply-data")) {
					useDataBinding = false; // this list uses a datasource, skip databinding
				}

				if (dataParent && dataParent[dataName]) {
					if (useDataBinding) {
						if (list.dataBinding) {
							// Check if the existing dataBinding is still for the same path - if not, unbind it;
							if (list.dataBinding.config.dataPath != editor.data.getDataPath(list)) {
								list.dataBinding.unbind(list);
								list.dataBinding = false;
							}
						}
						if (list.dataBinding) {
							editor.list.dataBindingSetter.call(list, dataParent[dataName]);
							list.dataBinding.setData(dataParent);
							list.dataBinding.set(dataParent[dataName]);
							list.dataBinding.resolve(true);
						} else {
							var listDataBinding;
							if (dataParent._bindings_ && dataParent._bindings_[dataName]) {
								listDataBinding = dataParent._bindings_[dataName];
								if (listDataBinding.config.mode == "field") {
									console.log("Warning: mixing field and list types for databinding.");
									if (Array.isArray(dataParent[dataName])) {
										listDataBinding.config.mode = "list";
										listDataBinding.mode = "list";
									}
								}
							} else {
								var bindingConfig    = {};
								for (var i in editor.settings.databind) {
									bindingConfig[i] = editor.settings.databind[i];
								}
								// bindingConfig.parentKey = list.getAttribute("data-simply-list") + "/" + j + "/";
								bindingConfig.data   = dataParent;
								bindingConfig.key    = dataName;
								bindingConfig.dataPath = editor.data.getDataPath(list);
								bindingConfig.getter = editor.list.dataBindingGetter;
								bindingConfig.setter = editor.list.dataBindingSetter;
								bindingConfig.mode   = "list";
								bindingConfig.attributeFilter = ["data-simply-selectable", "class", "tabindex", "data-simply-stashed", "contenteditable", "style", "data-simply-list-item"];
								listDataBinding = new dataBinding(bindingConfig);
							}
							listDataBinding.bind(list);
							list.addEventListener("databind:elementresolved", function(evt) {
								editor.list.emptyClass(this);
							});
						}
					} else {
						editor.list.dataBindingSetter.call(list, dataParent[dataName]);
					}
				}

				editor.list.emptyClass(list);
				list.addEventListener("keydown", editor.list.keyDownHandler);
			},
			emptyClass : function(list) {
				var hasChild = false;
				for (var m=0; m<list.childNodes.length; m++) {
					if (
						list.childNodes[m].nodeType == document.ELEMENT_NODE &&
						list.childNodes[m].getAttribute("data-simply-list-item")
					) {
						hasChild = true;
						continue;
					}
				}
				if (!hasChild) {
					if ("classList" in list) {
						list.classList.add("simply-empty");
					} else {
						list.className += " simply-empty";
					}
				}
			},
			detach : function(list) {
				// Remove the list from the DOM, do all the stuff and reinsert it, so we only redraw once for all our modifications.
				var nextNode = list.nextSibling;
				var listParent = list.parentNode;
				if (listParent) {
					list.reattach = function() {
						listParent.insertBefore(list, nextNode);
						editor.fireEvent("simply-selectable-inserted", document);
					};
					listParent.removeChild(list);
				} else {
					list.reattach = function() {
						editor.fireEvent("simply-selectable-inserted", document);
					};
				}
			},
			clear : function(list) {
				if (list.dataBinding) {
					list.dataBinding.pauseListeners(list);
				}
				// Remove the current list items to replace them with the new data;
				var children = list.querySelectorAll("[data-simply-list-item]");
				for (var i=0; i<children.length; i++) {
					if (children[i].parentNode == list) {
						list.removeChild(children[i]);
					}
				}
				if (list.dataBinding) {
					list.dataBinding.resumeListeners(list);
				}
			},
			initListItem : function(clone, useDataBinding, listDataItem) {
				var k;
				var dataFields = clone.querySelectorAll("[data-simply-field]");
				var savedParentKey = editor.settings.databind.parentKey;

				for (k=0; k<dataFields.length; k++) {
					editor.field.init(dataFields[k], listDataItem, useDataBinding);
					editor.settings.databind.parentKey = savedParentKey;
				}
				if (clone.nodeType == document.ELEMENT_NODE && clone.getAttribute("data-simply-field")) {
					editor.field.init(clone, listDataItem, useDataBinding);
				}

				var dataLists = clone.querySelectorAll("[data-simply-list]");
				for (k=0; k<dataLists.length; k++) {
					editor.list.init(dataLists[k], listDataItem, useDataBinding);
				}
				if (clone.nodeType == document.ELEMENT_NODE && clone.getAttribute("data-simply-list")) {
					editor.list.init(clone, listDataItem, useDataBinding);
				}

				editor.list.runScripts(clone);
			},
			set : function(list, listData) {
				if (list.dataBinding) {
					list.dataBinding.pauseListeners(list);
				}

				var previousStyle = list.getAttribute("style");
				list.style.height = list.offsetHeight + "px"; // this will prevent the screen from bouncing and messing up the scroll offset.
				editor.list.clear(list);
				editor.list.append(list, listData);
				list.setAttribute("style", previousStyle);
				editor.list.emptyClass(list);
				if (list.dataBinding) {
					list.dataBinding.resumeListeners(list);
				}
			},
			runScripts: function(node) {
				var scripts = node.querySelectorAll('script');
				var newNode;
				for (var i=0; i<scripts.length; i++) {
					newNode = document.createElement('script');
					if (scripts[i].getAttribute('src')) {
						newNode.src = scripts[i].getAttribute('src');
					}
					if (scripts[i].innerHTML) {
						newNode.innerHTML = scripts[i].innerHTML;
					}
					scripts[i].parentNode.appendChild(newNode);
					scripts[i].parentNode.removeChild(scripts[i]);
				}
			},
			cloneTemplate : function(template) {
				var clone;
				if ("importNode" in document) {
					clone = document.importNode(template.content, true);

					// Grr... android browser imports the nodes, except the contents of subtemplates. Find them and put them back where they belong.
					var originalTemplates = template.content.querySelectorAll("template");
					var importedTemplates = clone.querySelectorAll("template");

					for (i=0; i<importedTemplates.length; i++) {
						importedTemplates[i].innerHTML = originalTemplates[i].innerHTML;
					}
				} else {
					clone = document.createElement("DIV");
					for (e=0; e<template.contentNode.childNodes.length; e++) {
						var clonedNode = template.contentNode.childNodes[e].cloneNode(true);
						clone.appendChild(clonedNode);
					}
				}
				editor.list.runScripts(clone);
				return clone;
			},
			append : function(list, listData) {
				var e,j,l;
				var t, counter;
				var stashedFields, i, newData, dataPath;
				var listenersRemoved;

				if (!listData) {
					listData = [];
				}
				if (list.dataBinding) {
				//	list.dataBinding.pauseListeners(list);
					list.dataBinding.removeListeners(list);
					listenersRemoved = true;
				}

				list.dataSimplyPath = editor.data.getDataPath(list);
				editor.list.detach(list);

				if (list.dataBinding) {
					editor.bindingParents = [list.dataBinding.parentKey + list.dataBinding.key];
				} else {
					editor.bindingParents.push(list.getAttribute("data-simply-list"));
				}

				var listIndex = list.querySelectorAll(":scope > [data-simply-list-item]");

				var fragment = document.createDocumentFragment();
				fragment.dataSimplyPath = list.dataSimplyPath;

				list.warnedFieldDataBinding = false;

				if (!list.clones) {
					list.clones = {};
				}

				var listEntryMapping = list.getAttribute("data-simply-entry");
				var listDataSource = list.getAttribute("data-simply-data");

				var listDataGetter = function() {
					return listData;
				};

				for (j=0; j<listData.length; j++) {
					if (!listData[j]) {
						continue;
					}
					if (listEntryMapping) {
						if (!listData[j]._simplyConverted) {
							var entry = new Object(JSON.parse(JSON.stringify(listData[j])));
							entry[listEntryMapping] = listData[j];
							entry._simplyConverted = true;
							Object.defineProperty(entry, "_simplyConvertedParent", {
								get : listDataGetter
							});
							listData[j] = entry;
						}
					}

					editor.bindingParents.push(j + listIndex.length);
					var currentBinding = list.dataBinding;
					if (typeof currentBinding !== "undefined") {
						if (currentBinding.mode == "list") {
							if (currentBinding.get() != listData) {
								currentBinding.get().push(listData[j]);
							//	console.log("Appending items to existing data");
							}
						} else {
							if (!list.warnedFieldDataBinding) {
								console.log("Warning: Can't append list items to a field databinding");
								list.warnedFieldDataBinding = true;
							}
						}
					}

					editor.settings.databind.parentKey = editor.bindingParents.join("/") + "/"; // + list.getAttribute("data-simply-list") + "/" + j + "/";

					var requestedTemplate = listData[j]["data-simply-template"];

					if (!list.templates[requestedTemplate]) {
						requestedTemplate = list.defaultTemplate;
					}

					var clone;
					if ("importNode" in document) {
						if (list.clones[requestedTemplate]) {
							clone = list.clones[requestedTemplate].cloneNode(true);
						} else {
							clone = document.importNode(list.templates[requestedTemplate].content, true);
							// Grr... android browser imports the nodes, except the contents of subtemplates. Find them and put them back where they belong.
							var originalTemplates = list.templates[requestedTemplate].content.querySelectorAll("template");
							var importedTemplates = clone.querySelectorAll("template");

							for (i=0; i<importedTemplates.length; i++) {
								importedTemplates[i].innerHTML = originalTemplates[i].innerHTML;
							}
							list.clones[requestedTemplate] = clone.cloneNode(true);
						}

						clone.dataSimplyPath = list.dataSimplyPath;
						if (listDataSource) {
							editor.list.initListItem(clone, false, listData[j]);
						} else {
							editor.list.initListItem(clone, true, listData[j]);
						}

						editor.list.fixFirstElementChild(clone);

						counter = Object.keys(list.templates).length;

						if (counter > 1) {
							clone.firstElementChild.setAttribute("data-simply-template", requestedTemplate);
						}

						clone.firstElementChild.setAttribute("data-simply-list-item", true);
						clone.firstElementChild.setAttribute("data-simply-selectable", true);

						if (list.templateIcons[requestedTemplate]) {
							clone.firstElementChild.setAttribute("data-simply-list-icon", list.templateIcons[requestedTemplate]);
						}
						
						stashedFields = clone.firstElementChild.querySelectorAll("[data-simply-stashed]");
						for (i=0; i<stashedFields.length; i++) {
							stashedFields[i].removeAttribute("data-simply-stashed");
						}

						if (!listData[j]._bindings_) {
							newData = editor.list.get(clone.firstElementChild);
							dataPath = editor.data.getDataPath(clone.firstElementChild);
							editor.data.apply(newData, clone.firstElementChild);
							clone.firstElementChild.simplyData = newData[dataPath]; // optimize: this allows the databinding to cleanly insert the new item;
						}
						if (document.body.getAttribute("data-simply-edit")) {
							editor.editmode.makeEditable(clone);
						}

						fragment.appendChild(clone);

						editor.list.initLists(listData[j], clone);
					} else {
						for (e=0; e<list.templates[requestedTemplate].contentNode.childNodes.length; e++) {
							clone = list.templates[requestedTemplate].contentNode.childNodes[e].cloneNode(true);
							if (listDataSource) {
								editor.list.initListItem(clone, false, listData[j]);
							} else {
								editor.list.initListItem(clone, true, listData[j]);
							}
							editor.list.fixFirstElementChild(clone);

							counter = 0;
							for (t in list.templates) {
								counter++;
							}
							if (counter > 1) {
								clone.setAttribute("data-simply-template", requestedTemplate);
							}
							clone.setAttribute("data-simply-list-item", true);
							clone.setAttribute("data-simply-selectable", true);
							
							if (list.templateIcons[requestedTemplate]) {
								clone.firstElementChild.setAttribute("data-simply-list-icon", list.templateIcons[requestedTemplate]);
							}

							stashedFields = clone.querySelectorAll("[data-simply-stashed]");
							for (i=0; i<stashedFields.length; i++) {
								stashedFields[i].removeAttribute("data-simply-stashed");
							}

							if (!listData[j]._bindings_) {
								newData = editor.list.get(clone);
								dataPath = editor.data.getDataPath(clone);
								editor.data.apply(newData, clone);
								clone.simplyData = newData[dataPath]; // optimize: this allows the databinding to cleanly insert the new item;
							}

							if (document.body.getAttribute("data-simply-edit")) {
								editor.editmode.makeEditable(clone);
							}

							fragment.appendChild(clone);
							editor.list.initLists(listData[j], clone);
						}
					}

					editor.bindingParents.pop();
					editor.settings.databind.parentKey = editor.bindingParents.join("/") + "/"; // + list.getAttribute("data-simply-list") + "/" + j + "/";
				}

				list.appendChild(fragment);

				list.setAttribute("data-simply-selectable", true);
				editor.bindingParents.pop();
				editor.settings.databind.parentKey = editor.bindingParents.join("/") + "/"; // + list.getAttribute("data-simply-list") + "/" + j + "/";

				var hasChild = false;
				for (j=0; j<list.childNodes.length; j++) {
					if (
						list.childNodes[j].nodeType == document.ELEMENT_NODE &&
						list.childNodes[j].getAttribute("data-simply-list-item")
					) {
						hasChild = true;
						continue;
					}
				}
				if ("classList" in list) {
					if (!hasChild) {
						list.classList.add("simply-empty");
					} else {
						list.classList.remove("simply-empty");
					}
				} else {
					if (!hasChild) {
						list.className += " simply-empty";
					} else {
						list.className.replace(/ simply-empty/g, '');
					}
				}
				if (list.hasAttribute("data-simply-data") && list.dataBinding) {
					list.dataBinding.set(list.dataBinding.get());
					list.dataBinding.resolve(true);
				}
				list.reattach();
				if (list.dataBinding) {
					if (listenersRemoved) {
						var pauseCount = list.dataBindingPaused;
						list.dataBinding.addListeners(list);
						list.dataBindingPaused = pauseCount;
					}
					// list.dataBinding.resumeListeners(list);
				}
			}
		},
		field : {
			dataBindingGetter : function() {
				if (!this.getAttribute("data-simply-field") && this.getAttribute("data-simply-list")) {
					return editor.list.dataBindingGetter.call(this);
				}
				return editor.field.get(this);
			},
			dataBindingSetter : function(value) {
				if (!this.getAttribute("data-simply-field") && this.getAttribute("data-simply-list")) {
					return editor.list.dataBindingSetter.call(this, value);
				}
				return editor.field.set(this, value);
			},
			fieldTypes : {
				"img" : {
					get : function(field) {
						var result = editor.field.defaultGetter(field, ["src", "class", "alt", "title", ["data-simply-src"]]);
						if (result['data-simply-src']) {
							result.src = result['data-simply-src'];
							delete result['data-simply-src'];
						}
						if (field.simplyString) {
							return result.src;
						}
						return result;
					},
					set : function(field, data) {
						if (typeof data == "string") {
							field.simplyString = true;
							data = {"src" : data};
						}
						if (data) {
							data = JSON.parse(JSON.stringify(data));
							data['data-simply-src'] = data.src;
							delete(data.src);
							editor.field.defaultSetter(field, data);
							editor.responsiveImages.initImage(field);
						}
					},
					makeEditable : function(field) {
						editor.field.initHopeStub(field);
						field.setAttribute("data-simply-selectable", true);
					}
				},
				"iframe" : {
					get : function(field) {
						var result = editor.field.defaultGetter(field, ["src"]);
						if (field.simplyString) {
							return result.src;
						}
						return result;
					},
					set : function(field, data) {
						if (typeof data == "string") {
							field.simplyString = true;
							data = {"src" : data};
						}
						return editor.field.defaultSetter(field, data);
					},
					makeEditable : function(field) {
						field.contentEditable = true;
					}
				},
				"meta" : {
					get : function(field) {
						var result = editor.field.defaultGetter(field, ["content"]);
						if (field.simplyString) {
							return result.content;
						}
						return result;
					},
					set : function(field, data) {
						if (typeof data == "string") {
							field.simplyString = true;
							data = {"content" : data};
						}
						return editor.field.defaultSetter(field, data);
						
					},
					makeEditable : function(field) {
						field.contentEditable = true;
					}
				},
				"a" : {
					get : function(field) {
						var result = editor.field.defaultGetter(field, ["href", "class", "alt", "title", "innerHTML", "name", "rel", "target"]);
						if (result.rel == "nofollow") {
							result.nofollow = true;
						}
						if (result.target == "_blank") {
							result.newwindow = true;
						}
						delete result.rel;
						delete result.target;
						if (field.simplyString) {
							return result.href;
						}
						return result;
					},
					set : function(field, data) {
						if (typeof data == "string") {
							field.simplyString = true;
							return editor.field.defaultSetter(field, {href : data});
						}
						if (typeof data.name == "string") {
							data.id = data.name;
						}
						if (data.newwindow) {
							data.target = "_blank";
						}
						if (data.nofollow) {
							data.rel = "nofollow";
						}
						delete data.newwindow;
						delete data.nofollow;
						return editor.field.defaultSetter(field, data);
					},
					makeEditable : function(field) {
						field.addEventListener("click", function(evt) {
							evt.preventDefault();
						}, true);
						// field.addEventListener("dblclick", editor.editmode.followLink);

						if (field.getAttribute("data-simply-content") == "fixed") {
							editor.field.initHopeStub(field);
							field.setAttribute("data-simply-selectable", true);
						} else {
							editor.field.initHopeEditor(field);
						}
					}
				},
				"i.fa" : {
					makeEditable : function(field) {
						field.setAttribute("data-simply-selectable", true);
					}
				},
				"title" : {
					makeEditable : function(field) {
						field.contentEditable = true;
					}
				},
				"input[type=text],input:not([type]),input[type=hidden],textarea,input[type=number],input[type=date]"
					: { get : function(field) {
						return field.value;
					},
					set : function(field, data) {
						field.value = data;
					}
				},
				"input[type=radio]" : {
					get : function(field) {
						if (field.checked) {
							return field.value;
						} else {
							return field.simplyData;
						}
					},
					set : function(field, data) {
						if (data == field.value) {
							field.checked = true;
						} else {
							field.checked = false;
						}
						field.simplyData = data;
					}
				},
				"input[type=checkbox]" : {
					get : function(field) {
						if (field.getAttribute('value')) {
							if (field.checked) {
								return field.value;
							} else {
								return '';
							}
						} else {
							if (field.checked) {
								return 1;
							} else {
								return 0;
							}
						}
					},
					set : function(field, data) {
						if (field.hasAttribute('value')) {
							if (field.getAttribute('value') == data) {
								field.checked = true;
							} else {
								field.checked = false;
							}
						} else {
							if (data == 1) {
								field.checked = true;
							} else {
								field.checked = false;
							}
						}
					}
				},
				"select:not([multiple])" : {
					get : function(field) {
						return field.value;
					},
					set : function(field, data) {
						field.simplyValue = data; // keep the value, for async options that are loaded via dataSources;
						field.value = data;
						var options = field.querySelectorAll("option");
						for (var i=0; i<options.length; i++) {
							if (options[i].value == field.value) {
								options[i].selected = true;
								options[i].setAttribute("selected", true);
							} else {
								options[i].selected = false;
								options[i].removeAttribute("selected");
							}
						}
					}
				},
				"select[multiple]" : {
					get : function(field) {
						var result = [];
						var selected = field.selectedOptions;
						for (var i=0; i<selected.length; i++) {
							result.push(selected[i].value);
						}
						return result;
					},
					set : function(field, data) {
						field.simplyValue = data; // keep the value, for async options that are loaded via dataSources;
						var options = field.querySelectorAll("option");
						for (var i=0; i<options.length; i++) {
							if (data.indexOf(options[i].value) > -1) {
								options[i].selected = true;
								options[i].setAttribute("selected", true);
							} else {
								options[i].selected = false;
								options[i].removeAttribute("selected");
							}
						}
					}
				},
				"option" : {
					get : function(field) {
						var result = editor.field.defaultGetter(field, ["innerHTML"]);
						result.value = field.value;
						if (field.simplyString) {
							return result.value;
						}
						return result;
					},
					set : function(field, data) {
						if (typeof data === "string") {
							field.simplyString = true;
							if (field.getAttribute("data-simply-content") != "fixed") {
								field.innerHTML = data;
							}
							field.value = data;
						} else {
							editor.field.defaultSetter(field, data);
						}
					}
				},
				"[data-simply-content='template']" : {
					get : function(field) {
						if (editor.data.getDataPath(field) == field.storedPath) {
							return field.storedData;
						}
						if (field.hasAttribute("data-simply-default-value")) {
							return field.getAttribute("data-simply-default-value");
						}
					},
					set : function(field, data) {
						editor.list.parseTemplates(field);
						field.innerHTML = '';

						var savedBindingParents = editor.bindingParents;
						var savedParentKey = editor.settings.databind.parentKey;
						var fieldPath = editor.data.getDataPath(field);

						if (!field.templates[data]) {
							var defaultTemplate = field.getAttribute("data-simply-default-template");
							if (defaultTemplate && field.templates[defaultTemplate]) {
								// We don't have a template for this value, but there is a default template set;
								field.templates[data] = field.templates[defaultTemplate];
							}
						}

						if (field.templates[data]) {
							var clone = editor.list.cloneTemplate(field.templates[data]);
							field.appendChild(clone);
							for (var i=0; i<field.childNodes.length; i++) {
								if (field.childNodes[i].nodeType == document.ELEMENT_NODE) {
									if (field.dataBinding) {
										// Bind the subfields of the template to the same data-level as this field;

										var fieldData = {};
										fieldData[fieldPath] = field.fieldDataParent;
/*
										var fieldData = {};
										fieldData[fieldPath] = editor.currentData[fieldPath];
										// split the binding parents into seperate entries and remove the first empty entry;
										var subkeys = field.dataBinding.parentKey.replace(/\/$/,'').split("/");

//										var subkeys = savedBindingParents.join("/").replace(/\/$/,'').split("/");
										if (subkeys[0] === "") {
											subkeys.shift();
										}

										if (savedParentKey != field.dataBinding.parentKey) {
											editor.bindingParents = ["/" + subkeys.join("/")];
											editor.settings.databind.parentKey = field.dataBinding.parentKey;
										}

//										var fieldKeys = field.getAttribute('data-simply-field').split(".");
//										fieldKeys.pop();

//										if (fieldKeys.length && (subkeys.join(".") == fieldKeys.join("."))) {
//										} else {
											var subkey = subkeys.shift();
											if (fieldData[fieldPath] && fieldData[fieldPath][subkey]) {
												fieldData[fieldPath] = fieldData[fieldPath][subkey];
											} else {
												fieldData[fieldPath] = {};
											}
//										}

*/
										editor.data.apply(fieldData, field.childNodes[i]);
									} else {
										editor.data.apply(editor.currentData, field.childNodes[i]);
									}
								}
							}
						}
						field.storedPath = fieldPath;
						field.storedData = data;
						if (document.body.getAttribute("data-simply-edit")) {
							editor.editmode.makeEditable(field);
						}
						editor.bindingParents = savedBindingParents;
						editor.settings.databind.parentKey = savedParentKey;
					},
					makeEditable : function(field) {
						return true;
					}
				},
				"[data-simply-content='attributes']" : {
					get : function(field) {
						var attributes = field.getAttribute("data-simply-attributes");
						if (attributes) {
							attributes = attributes.split(" ");
						} else {
							// If none were set, default to all the non-simply-attributes;
							attributes = [];
							for (var i=0; i<field.attributes.length; i++) {
								if (!field.attributes[i].nodeName.match(/^data-simply/)) {
									attributes.push(field.attributes[i].nodeName);
								}
							}
						}
						var result = editor.field.defaultGetter(field, attributes);
						if (field.simplyString && (attributes.length === 1)) {
							return result[attributes[0]];
						}
						return result;
					},
					set : function(field, data) {
						var attributes = field.getAttribute("data-simply-attributes");
						if (attributes) {
							attributes = attributes.split(" ");
						} else {
							// If none were set, default to all the non-simply-attributes;
							attributes = [];
							for (var i=0; i<field.attributes.length; i++) {
								if (!field.attributes[i].nodeName.match(/^data-simply/)) {
									attributes.push(field.attributes[i].nodeName);
								}
							}
						}
						if (typeof data === "boolean") {
							data = data.toString();
						}

						if ((typeof data === "string" || typeof data === "number") && (attributes.length === 1)) {
							field.simplyString = true;
							var newdata = {};
							newdata[attributes[0]] = data;
							return editor.field.defaultSetter(field, newdata);
						}

						return editor.field.defaultSetter(field, data, attributes);
					},
					makeEditable : function(field) {
						field.setAttribute("data-simply-selectable", true);
					}
				}
			},
			initHopeEditor : function(field) {
				if (typeof hope === "undefined") {
					window.setTimeout(function() {
						editor.field.initHopeEditor(field);
					}, 300);
					return;
				}
				if (typeof field.hopeEditor !== "undefined") {
					return;
				}

				// This allows us to handle empty-ish fields better; data-simply-hope will get a min-width and min-height, collapsed will get inline-block;
				field.setAttribute('data-simply-hope', true);
				if (getComputedStyle(field).display == "inline" && field.offsetWidth === 0) {
					field.setAttribute('data-simply-collapsed', true);
				}
				if (field.innerHTML.trim() === "") {
					field.innerHTML = "";
				}

				field.hopeContent = document.createElement("textarea");
				field.hopeMarkup = document.createElement("textarea");
				field.hopeRenderedSource = document.createElement("DIV");
				field.hopeEditor = hope.editor.create( field.hopeContent, field.hopeMarkup, field, field.hopeRenderedSource );
				field.hopeEditor.field = field;
				field.hopeEditor.field.addEventListener("DOMCharacterDataModified", function() {
					field.hopeEditor.needsUpdate = true;
				});
				field.addEventListener("slip:beforereorder", function(evt) {
					var rect = this.getBoundingClientRect();
					if (
						this.clickStart &&
						this.clickStart.x > rect.left &&
						this.clickStart.x < rect.right &&
						this.clickStart.y < rect.bottom &&
						this.clickStart.y > rect.top
					) {
						// this will prevent triggering list sorting when using tap-hold on text;
						// the check of the clientrect will allow a click on the list item marker to continue, because it is positioned out of bounds;
						evt.preventDefault(); // this will prevent triggering list sorting when using tap-hold on text;
						return false;
					}
				}, false);
				field.addEventListener("slip:beforeswipe", function(evt) {
					var rect = this.getBoundingClientRect();
					if (
						this.clickStart &&
						this.clickStart.x > rect.left &&
						this.clickStart.x < rect.right &&
						this.clickStart.y < rect.bottom &&
						this.clickStart.y > rect.top
					) {
						// this will prevent triggering list swiping;
						// the check of the clientrect will allow a click on the list item marker to continue, because it is positioned out of bounds;
						evt.preventDefault(); // this will prevent triggering list swiping on text;
						return false;
					}
				}, false);
			},
			initHopeStub : function(field) {
				if (typeof field.hopeEditor !== "undefined") {
					return;
				}
				field.hopeEditor = {
					field : field,
					parseHTML : function(){},
					fragment : {
						has : function() {
							return false;
						}
					},
					getBlockAnnotation : function() {
						return false;
					},
					currentRange : false,
					selection : {
						getRange : function() {
							return false;
						},
						updateRange : function() {}
					},
					update : function() {},
					showCursor : function() {}
				};
			},
			matches : function(el, selector) {
				var p = Element.prototype;
				var f = p.matches || p.webkitMatchesSelector || p.mozMatchesSelector || p.msMatchesSelector || function(s) {
					return [].indexOf.call(document.querySelectorAll(s), this) !== -1;
				};
				return f.call(el, selector);
			},
			defaultGetter : function(field, attributes) {
				var result = {};
				var contentType = field.getAttribute('data-simply-content');
				if (field.dataBinding) {
					var currentValue = field.dataBinding.get();
					if (typeof currentValue === "object" && currentValue !== null) {
						result = JSON.parse(JSON.stringify(currentValue)); // Start with the existing data if there to prevent destroying data that is not part of our scope;
					}
				}
				for (var i=0; i<attributes.length; i++) {
					attr = attributes[i];
					if (attr == "innerHTML") {
						if (contentType != "fixed") {
							if (contentType == 'text') {
								result.innerHTML = editor.field.getTextContent(field);
							} else {
								result.innerHTML = editor.field.getInnerHTML(field);
								if (field.querySelector("[data-simply-field]")) {
									console.log("Warning: This field contains another field in its innerHTML - did you mean to set the data-simply-content attribute for this field to 'fixed' or 'attributes'?");
									console.log(field);
								}
							}
						}
					} else {
						result[attr] = field.getAttribute(attr);
					}
				}
				return result;
			},
			defaultSetter : function(field, data, attributes) {
				var contentType = field.getAttribute("data-simply-content");
				if ((typeof data === "string" || data instanceof String) && attributes && attributes.length == 1) {
					field.simplyString = true;
					var newData = {};
					newData[attributes[0]] = data;
					data = newData;
				}
				if (typeof data === "string") {
					console.log("Warning: A string was given to a field that expects an object - did you maybe use the same field name on different kinds of elements?");
					return;
				}
				if (data instanceof String) {
					console.log("Warning: A string was given to a field that expects an object - did you maybe use the same field name on different kinds of elements?");
					return;
				}
				for (var attr in data) {
					if (attributes && attributes.indexOf(attr) < 0) {
						continue;
					}
					if (attr == "innerHTML") {
						if (contentType != "fixed") {
							if (contentType == "text") {
								field.textContent = data[attr];
							} else {
								field.innerHTML = data[attr];
								editor.responsiveImages.init(field);
							}
							if (field.hopeEditor) {
								field.hopeEditor.needsUpdate = true;
							}
						}
					} else {
						if (data[attr] !== null) {
							field.setAttribute(attr, data[attr]);
						} else {
							field.removeAttribute(attr);
						}
					}
				}
			},
			registerType : function(fieldType, getter, setter, editmode) {
				editor.field.fieldTypes[fieldType] = {
					get : getter,
					set : setter,
					makeEditable : editmode
				};
			},
			set : function(field, data) {
				if (typeof data === "undefined") {
					return;
				}
				if (!editor.selectionchangeTimer) {
					editor.selectionchangeTimer = window.setTimeout(function() {
						editor.fireEvent("selectionchange", document); // fire this after we're done. Using settimeout so it will run afterwards.
						editor.selectionchangeTimer = false;
					}, 0);
				}
				if (!field.simplySetter) {
					for (var i in editor.field.fieldTypes) {
						if (editor.field.matches(field, i)) {
							if (typeof editor.field.fieldTypes[i].set === "function") {
								field.simplySetter = editor.field.fieldTypes[i].set;
							}
						}
					}
				}

				var transformer = field.getAttribute('data-simply-transformer');
				if (transformer) {
					if (editor.transformers[transformer] && (typeof editor.transformers[transformer].render === "function")) {
						data = editor.transformers[transformer].render.call(field, data);
					}
				}

				if (field.simplySetter) {
					return field.simplySetter(field, data);
				} else {
					editor.field.defaultSetter(field, {innerHTML : data});
				}
				// field.innerHTML = data;
				editor.responsiveImages.init(field);
				if (field.hopeEditor) {
					field.hopeEditor.needsUpdate = true;
				}
			},
			getTextContent : function(field) {
				var div = document.createElement('div');
				div.innerHTML = field.innerHTML;
				var els = div.querySelectorAll('br,p');
				for (var i=0,l=els.length; i<l; i++) {
					var el = els.item(i);
					if (el.nextSibling) {
						var newLine = document.createTextNode("\n");
						el.parentElement.insertBefore(newLine, el.nextSibling);
					}
				}
				return div.textContent;
			},
			getInnerHTML : function(field) {
				// misc cleanups to revert any changes made by simply edit - this should return a pristine version of the content;
				if (!field.querySelectorAll("img[data-simply-src]").length) {
					return field.innerHTML;
				} else {
					// There are responsive images in the field; clean them up to return to a pristine state and return that;
					var fieldClone = field.cloneNode(true);
					var responsiveImages = fieldClone.querySelectorAll("img[data-simply-src]");
					for (var i=0; i<responsiveImages.length; i++) {
						responsiveImages[i].removeAttribute("src");
						responsiveImages[i].removeAttribute("sizes");
						responsiveImages[i].removeAttribute("srcset");
					}
					return fieldClone.innerHTML;
				}
			},
			get : function(field) {
				if (!field.simplyGetter) {
					for (var i in editor.field.fieldTypes) {
						if (editor.field.matches(field, i)) {
							if (typeof editor.field.fieldTypes[i].get === "function") {
								field.simplyGetter = editor.field.fieldTypes[i].get;
							}
						}
					}
				}
				var result;
				if (field.simplyGetter) {
					result = field.simplyGetter(field);
				} else {
					result = editor.field.defaultGetter(field, ['innerHTML']);
					result = result.innerHTML;
				}

				var transformer = field.getAttribute('data-simply-transformer');
				if (transformer) {
					if (editor.transformers[transformer] && (typeof editor.transformers[transformer].extract === "function")) {
						result = editor.transformers[transformer].extract.call(field, result);
					}
				}
				return result;
			},
			makeEditable : function(field) {
				if (field.dataBinding) {
					field.dataBinding.pauseListeners(field);
					window.setTimeout(function() {
						field.dataBinding.resumeListeners(field);
					});
				}
				var editable;
				for (var i in editor.field.fieldTypes) {
					if (editor.field.matches(field, i)) {
						if (typeof editor.field.fieldTypes[i].makeEditable === "function") {
							editable = editor.field.fieldTypes[i].makeEditable;
						}
					}
				}
				if (editable) {
					return editable(field);
				}
				if (field.getAttribute("data-simply-content") == "fixed") {
					editor.field.initHopeStub(field);
					field.setAttribute("data-simply-selectable", true);
				} else {
					editor.field.initHopeEditor(field);
				}
			},
			init : function(field, dataParent, useDataBinding) {
				for (var t in editor.field.fieldTypes) {
					if (editor.field.matches(field, t)) {
						if (typeof editor.field.fieldTypes[t].get === "function") {
							field.simplyGetter = editor.field.fieldTypes[t].get;
						}
						if (typeof editor.field.fieldTypes[t].set === "function") {
							field.simplySetter = editor.field.fieldTypes[t].set;
						}
					}
				}

				var dataName = field.getAttribute("data-simply-field");

				var fieldDataParent = dataParent;
				field.fieldDataParent = fieldDataParent;

				var dataKeys = dataName.split(".");
				dataName = dataKeys.pop();
				for (var j=0; j<dataKeys.length; j++) {
					if (!dataParent[dataKeys[j]]) {
						dataParent[dataKeys[j]] = {};
					}
					editor.data.bindAsParent(dataParent, dataKeys[j]);
					dataParent = dataParent[dataKeys[j]];
					editor.settings.databind.parentKey += dataKeys[j] + "/";
				}
				if (
					(typeof dataParent[dataName] === "undefined") ||
					(!dataParent[dataName] && !Object.keys(dataParent).length) ||
					(dataParent[dataName] === null)
				) {
					dataParent[dataName] = editor.field.get(field);
				}
				if (dataParent[dataName] !== null) {
					if (useDataBinding) {
						if (field.dataBinding) {
							// Check if the existing dataBinding is still for the same path - if not, unbind it;
							if (field.dataBinding.config.dataPath != editor.data.getDataPath(field)) {
								field.dataBinding.unbind(field);
								field.dataBinding = false;
							}
						}

						if (field.dataBinding) {
							field.dataBinding.setData(dataParent);
							field.dataBinding.set(dataParent[dataName]);
							field.dataBinding.resolve(true);
						} else {
							var fieldDataBinding;
							if (dataParent._bindings_ && dataParent._bindings_[dataName]) {
								fieldDataBinding = dataParent._bindings_[dataName];
							} else {
								var bindingConfig    = {};
								for (var i in editor.settings.databind) {
									bindingConfig[i] = editor.settings.databind[i];
								}
								// bindingConfig.parentKey = list.getAttribute("data-simply-list") + "/" + j + "/";
								bindingConfig.data   = dataParent;
								bindingConfig.key    = dataName;

								bindingConfig.fieldDataParent = fieldDataParent;

								bindingConfig.dataPath = editor.data.getDataPath(field);
								bindingConfig.getter = editor.field.dataBindingGetter;
								bindingConfig.setter = editor.field.dataBindingSetter;
								bindingConfig.mode   = "field";
								bindingConfig.attributeFilter = ["data-simply-selectable", "tabindex", "data-simply-stashed", "contenteditable", "data-simply-list-item"];
								fieldDataBinding = new dataBinding(bindingConfig);
							}
							fieldDataBinding.bind(field);
						}
					} else {
						editor.field.set(field, dataParent[dataName]);
					}
				}
			}
		},
		fireEvent : function(evtname, target, eventData) {
			var event; // The custom event that will be created
			if (document.createEvent) {
				event = document.createEvent("HTMLEvents");
				event.initEvent(evtname, true, true);
			} else {
				event = document.createEventObject();
				event.eventType = evtname;
			}

			event.data = eventData;
			event.eventName = evtname;

			if (document.createEvent) {
				target.dispatchEvent(event);
			} else {
				// target.fireEvent("on" + event.eventType, event);
			}
			return event;
		},
		loadBaseStyles : function() {
			var baseStyles = document.createElement("link");
			var cssuri = 'data:text/css,'+ encodeURIComponent(
			'.simply-text-align-left { text-align: left; }'  +
			'.simply-text-align-right { text-align: right; }' +
			'.simply-text-align-center { text-align: center; }' +
			'.simply-text-align-justify { text-align: justify; }' +
			'.simply-image-align-left { float: left; }' +
			'.simply-image-align-right { float: right; }' +
			'.simply-image-align-middle { vertical-align: middle; }' +
			'.simply-image-align-top { vertical-align: top; }' +
			'.simply-image-align-bottom { vertical-align: bottom; }' +
			'.simply-overflow-hidden { overflow: hidden; }' +
			'');
			baseStyles.setAttribute("href", cssuri);
			baseStyles.setAttribute("rel", "stylesheet");
			baseStyles.setAttribute("type", "text/css");
			document.getElementsByTagName("HEAD")[0].appendChild(baseStyles);
		},
		init : function(config) {
			document.createElement("template");
			if (config.toolbars) {
				for (i=0; i<config.toolbars.length; i++) {
					editor.editmode.toolbars.push(config.toolbars[i]);
				}
			}
			editor.loadBaseStyles();

			// convert URL for the endpoint to an absolute path;
			if (typeof config.endpoint !== 'undefined' && config.endpoint) {
				var parser = document.createElement("A");
				parser.href = config.endpoint;
				config.endpoint = parser.href;
			}

			editor.profile = config.profile;
			editor.storage = storage.init(config.endpoint);
			editor.fireEvent("simply-storage-init", document);

			// Add binding for editor.pageData so it follows the current path of the document;
			Object.defineProperty(editor, "pageData", {
				get : function() {
					var path = editor.data.getDataPath(document);
					if (typeof editor.currentData[path] === "undefined") {
						editor.currentData[path] = {};
					}
					return editor.currentData[path];
				},
				set : function(data) {
					var path = editor.data.getDataPath(document);
					if (typeof editor.currentData[path] === "undefined") {
						editor.currentData[path] = {};
					}
					if (typeof data === "object") {
						for (var i in data) {
							editor.currentData[path][i] = data[i];
						}
					}
				}
			});

			// Add databinding and load data afterwards
			// editor.loadScript(editor.baseURLClean + "simply/databind.js" + (editor.profile == "dev" ? "?t=" + (new Date().getTime()) : ""), editor.data.load);
			//editor.loadScript(editor.baseURLClean + "simply/databind.js", editor.data.load);
			editor.data.load();
		},
		loadScript : function(src, callback) {
			if (!document.head.querySelector('script[src="'+src+'"]')) {
				var scriptTag = document.createElement("SCRIPT");
				scriptTag.setAttribute("src", src);
				scriptTag.addEventListener("load", function(evt) {
					if (typeof callback === "function") {
						callback();
					}
				});
				document.head.appendChild(scriptTag);
			}
		},
		loadStyleSheet : function(src, attributes, target) {
			if (!target) {
				target = document.head;
			}
			var styleTag = document.createElement("LINK");
			styleTag.setAttribute("rel", "stylesheet");
			styleTag.setAttribute("type", "text/css");
			if (typeof attributes !== 'undefined'){
				for (var key in attributes) {
					styleTag.setAttribute(key, attributes[key]);
				}
			}
			styleTag.href = src;
			target.appendChild(styleTag);
		},
		editmode : {
			toolbars : [],
			loadToolbarList : function(toolbarList) {
				if (!editor.toolbarsContainer) {
					editor.toolbarsContainer = editor.editmode.getToolbarsContainer();
				}
				var url = toolbarList.shift();
				var i;
				var http = new XMLHttpRequest();
				var editorCss;
				if (editor.profile == "dev") {
					url += "?t=" + (new Date().getTime());
				} else {
					url += "?v=" + editor.version;
				}

				http.open("GET", url, true);

				http.onreadystatechange = function() {//Call a function when the state changes.
					if(http.readyState == 4) {
						if ((http.status > 199) && (http.status < 300)) { // accept any 2xx http status as 'OK';
							var toolbars = document.createElement("TEMPLATE");
							toolbars.innerHTML = http.responseText;

							if (!("content" in toolbars)) {
								var fragment = document.createDocumentFragment();
								while (toolbars.children.length) {
									fragment.appendChild(toolbars.children[0]);
								}
								toolbars.content = fragment;
							}
							var scriptTags = toolbars.content.querySelectorAll("SCRIPT");
							for (i=0; i<scriptTags.length; i++) {
								scriptTags[i].parentNode.removeChild(scriptTags[i]);
							}

							var toolbarNode = document.importNode(toolbars.content, true);
							var newToolbars = toolbarNode.querySelectorAll(".simply-toolbar,.simply-dialog-body");
							editor.toolbarsContainer.appendChild(toolbarNode);

							for (i=0; i<scriptTags.length; i++) {
								var newNode = document.createElement("SCRIPT");
								if (scriptTags[i].getAttribute('src')) {
									newNode.src = scriptTags[i].getAttribute('src');
								}
								if (scriptTags[i].innerHTML) {
									newNode.innerHTML = scriptTags[i].innerHTML;
								}
								document.head.appendChild(newNode);
							}

							for (i=0; i<newToolbars.length; i++) {
								editor.toolbar.init(newToolbars[i]);
							}
						} else if (http.status === 0 || http.status === 403) {
							console.log("Toolbar load got status 0, XHR probably failed because of an invalid or expired API key.");
							alert("Unable to load SimplyEdit, please check that your API key is valid for this domain.");
							editorCss = document.head.querySelector("link[href='" + editor.baseURL + "simply/css/editor.v9.css" + "']");
							if (editorCss) {
								editorCss.parentNode.removeChild(editorCss);
							}
							return;
						} else {
							console.log("Warning: toolbar did not load.");
						}
						if (toolbarList.length) {
							editor.editmode.loadToolbarList(toolbarList);
						} else {
							editor.fireEvent("simply-toolbars-loaded", document);
						}
					}
				};
				http.send();
			},
			getToolbarsContainer : function() {
				var toolbarsContainer = document.querySelector("#simply-editor");
				if (!toolbarsContainer) {
					toolbarsContainer = document.createElement("DIV");
					toolbarsContainer.id = "simply-editor";
					document.body.appendChild(toolbarsContainer);
				}

				if (scriptEl.hasAttribute("data-simply-shadow-toolbars")) {
					if (!toolbarsContainer.shadowRoot && toolbarsContainer.attachShadow) {
						toolbarsContainer.attachShadow({mode: "open"});
					}
					if (toolbarsContainer.shadowRoot) {
						toolbarsContainer = toolbarsContainer.shadowRoot;
					}
				}
				if (!toolbarsContainer.getElementById) {
					toolbarsContainer.getElementById = function(id) {
						return document.getElementById(id);
					};
				}

				return toolbarsContainer;
			},
			init : function() {
				if (editor.readOnly) {
					alert("Can't start editmode, editor is in read only mode. Do you have private browsing on?");
					return;
				}
				editor.toolbarsContainer = editor.editmode.getToolbarsContainer();
				var loadToolbars = function() {
					if (!editor.toolbar || (typeof muze === "undefined")) {
						// Main toolbar code isn't loaded yet, delay a bit;
						window.setTimeout(loadToolbars, 100);
						return;
					}

					editor.editmode.loadToolbarList(editor.editmode.toolbars.slice()); // slice to copy the toolbars;
					editor.editmode.toolbarMonitor();
				};

				// Add slip.js for sortable items;
				editor.loadScript(editor.baseURL + "simply/slip.js" + (editor.profile == "dev" ? "?t=" + (new Date().getTime()) : "?v=" + editor.version));

				// Add hope
				editor.loadScript(editor.baseURL + "hope/hope.packed.js");

				// Add editor stylesheet
				editor.loadStyleSheet(editor.baseURL + "simply/css/editor.v9.css", {}, editor.toolbarsContainer);

				// Add editor stylesheet
				editor.loadStyleSheet(editor.baseURL + "simply/css/editor.v9.css", {}, document.head); // FIXME: split out the fields-styling for the styles that go into the head; Add all the other styles to the toolbars only.

				// Add font awesome
				// FIXME: font-face directive is not executed in the shadow dom.
				editor.loadStyleSheet("https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.4.0/css/font-awesome.min.css",{
					'integrity': 'sha256-k2/8zcNbxVIh5mnQ52A0r3a6jAgMGxFJFE2707UxGCk=',
					'crossorigin':"anonymous"
				}, editor.toolbarsContainer);

				editor.loadStyleSheet("https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.4.0/css/font-awesome.min.css",{
					'integrity': 'sha256-k2/8zcNbxVIh5mnQ52A0r3a6jAgMGxFJFE2707UxGCk=',
					'crossorigin':"anonymous"
				}, document.head);

				// Add legacy scripts
				editor.loadScript(editor.baseURL + "simply/scripts.js");

				// Add toolbar scripts
				editor.loadScript(editor.baseURL + "simply/toolbars.js");

				var handleBeforeUnload = function(evt) {
					if (editor.editmode.isDirty()) {
						var message = "You have made changes to this page, if you leave these changes will not be saved.";
						evt = evt || window.event;
						// For IE and Firefox prior to version 4
						if (evt) {
							evt.returnValue = message;
						}
						// For Safari
						return message;
					}
				};

				document.body.setAttribute("data-simply-edit", true);

				/* Prevent tap-hold contextmenu for chrome/chromebook */
				(function() {
					var touching = false;

					document.addEventListener("touchstart", function(evt) {
						touching = true;
					});
					document.addEventListener("touchend", function(evt) {
						touching = false;
					});
					window.addEventListener("contextmenu", function(evt) {
						if (touching) {
							evt.preventDefault();
						}
					});
				}());

				document.body.onbeforeunload = handleBeforeUnload; // Must do it like this, not with addEventListener;
				editor.fireEvent("simply-editmode", document);	
				loadToolbars();

			},
			followLink : function(evt) {
				var target = evt.target;
				if (target.tagName.toLowerCase() !== "a") {
					target = this;
				}

				if (
					target.pathname
				) {
					var pathname = target.pathname;
					var hostname = target.hostname;
					var extraCheck = true;
					if (typeof editor.storage.checkJail === "function") {
						extraCheck = editor.storage.checkJail(target.href);
					}
						
					if (extraCheck && (hostname == document.location.hostname) && (typeof editor.currentData[target.pathname] == "undefined")) {
						editor.storage.page.save(target.href);
						evt.preventDefault();
					} else {
						// FIXME: check for dirty fields and stash/save the changes
						document.location.href = target.href + "#simply-edit";
					}
				}
			},
			makeEditable : function(target) {
				var i;

				var dataFields = target.querySelectorAll("[data-simply-field]");
				for (i=0; i<dataFields.length; i++) {
					editor.field.makeEditable(dataFields[i]);
					// FIXME: Add support to keep fields that point to the same field within the same path in sync here;
				}
				if (target.getAttribute && target.getAttribute("data-simply-field")) {
					editor.field.makeEditable(target);
				}
				document.body.addEventListener("dragover", function(evt) {
					evt.preventDefault();
				});

				var dataLists = target.querySelectorAll("[data-simply-list]");
				for (i=0; i<dataLists.length; i++) {
					dataLists[i].setAttribute("data-simply-selectable", true);
				}

				var handleClick = function(event) {
					event.preventDefault();
				};

				target.addEventListener("dblclick", function(event) {
					if (event.target.tagName.toLowerCase() === "a") {
						editor.editmode.followLink(event);
					}
				}, true);

				target.addEventListener("click", function(event) {
					if (event.target.tagName.toLowerCase() === "a") {
						if (editor.node.hasSimplyParent(event.target) || editor.node.isSimplyParent(event.target)) {
							handleClick(event);
						}
					}
					if (event.target.tagName.toLowerCase() === "input" || event.target.tagName.toLowerCase() === "textarea") {
						// don't prevent the click on inputs or textareas, allow them to work normally.
						return;
					}
					if (editor.node.isSimplyParent(event.target)) {
						handleClick(event);
					}
				});

				// FIXME: Have a way to now init plugins as well;
				editor.editmode.sortable(target);

				// Disable object resizing for Firefox;
				document.execCommand("enableObjectResizing", false, false);
			},
			sortable : function(target) {
				if (!window.Slip) {
					window.setTimeout(function() {
						editor.editmode.sortable(target);
					}, 500);

					return;
				}

				var list = target.querySelectorAll("[data-simply-sortable]");
				
				var preventDefault = function(evt) {
					evt.preventDefault();
				};
				
				var hideToolbar = function() {
					if (editor.context.toolbar.hide) {
						return;
					}
					editor.context.toolbar.hide = true;
					editor.context.show();
				};
				var showToolbar = function() {
					editor.context.toolbar.hide = false;
				};
				document.addEventListener("slip:beforereorder", hideToolbar);
				document.addEventListener("slip:reorder", showToolbar);

				var addBeforeOrderEvent = function(e) {
					var sublists = this.querySelectorAll("[data-simply-sortable]");
					for (var j=0; j<sublists.length; j++) {
						sublists[j].addEventListener('slip:beforereorder', preventDefault);
						sublists[j].addEventListener('slip:beforeswipe', preventDefault);
					}
				};
				var removeBeforeOrderEvent = function(e) {
					var sublists = this.querySelectorAll("[data-simply-sortable]");
					for (var j=0; j<sublists.length; j++) {
						sublists[j].removeEventListener('slip:beforereorder', preventDefault);
						sublists[j].removeEventListener('slip:beforeswipe', preventDefault);
					}
					return false;
				};

				var removeSelection = function() {
					vdSelectionState.remove();
					window.getSelection().removeAllRanges();
					editor.context.update();
				};
				var slipReorderHandler = function(e) {
					e.target.parentNode.insertBefore(e.target, e.detail.insertBefore);
					window.setTimeout(removeSelection, 1);
					return false;
				};

				for (var i=0; i<list.length; i++) {
					list[i].addEventListener('slip:beforereorder', addBeforeOrderEvent, false);
					list[i].addEventListener('slip:beforeswipe', addBeforeOrderEvent, false);
					list[i].addEventListener('slip:reorder', slipReorderHandler);
					new Slip(list[i]);
				}

				if (typeof document.simplyRemoveBeforeOrderEvent === "undefined") {
					document.simplyRemoveBeforeOrderEvent = removeBeforeOrderEvent;
					document.addEventListener("mouseup", removeBeforeOrderEvent, false);
					document.addEventListener("touchend", removeBeforeOrderEvent, false);
				}
			},
			isDirty : function() {
				editor.data.stash();
				var newData = localStorage.data;
				var oldData = editor.data.stringify(JSON.parse(editor.loadedData));
				if (newData != oldData) {
					return true;
				}
				return false;
			},
			stop : function() {
				var redirect = function() {
					document.location.href = document.location.href.split("#")[0];
				};
				if (typeof editor.storage.disconnect !== "function") {
					editor.storage.disconnect = redirect;
				}
				if (editor.editmode.isDirty()) {
					var message = "You have made changes to this page, if you log out these changes will not be saved. Log out?";
					if (confirm(message)) {
						editor.editmode.isDirty = function() { return false; };
						editor.storage.disconnect(redirect);
					}
				} else {
					editor.storage.disconnect(redirect);
				}
			},
			toolbarMonitor : function() {
				var target = editor.toolbarsContainer.querySelector('#simply-main-toolbar');
				if (!target) {
					window.setTimeout(editor.editmode.toolbarMonitor, 100);
					return false;
				}

				var setBodyTop = function() {
					var style = document.head.querySelector("#simply-body-top");
					if (!style) {
						style = document.createElement("style");
						style.setAttribute("type", "text/css");

						style.id = "simply-body-top";
						document.head.appendChild(style);
					}
					if (editor.toolbarsContainer.getElementById("simply-main-toolbar")) {
						var toolbarHeight = editor.toolbarsContainer.getElementById("simply-main-toolbar").offsetHeight;
						style.innerHTML = "html:before { display: block; width: 100%; height: " + toolbarHeight + "px; content: ''; }";
					}
				};

				// create an observer instance
				var observer = new MutationObserver(setBodyTop);

				// configuration of the observer:
				var config = { childList: true, subtree: true, attributes: true, characterData: true };

				// pass in the target node, as well as the observer options
				observer.observe(target, config);

				window.setTimeout(setBodyTop, 100);
			}
		},
		responsiveImages : {
			getEndpoint  : function() {
				var imagesPath = document.querySelector("[data-simply-images]") ? document.querySelector("[data-simply-images]").getAttribute("data-simply-images") : null;
				if (typeof imagesPath !== 'undefined' && imagesPath) {
					var parser = document.createElement("A");
					parser.href = imagesPath;
					imagesPath = parser.href;
				}
				return imagesPath;
			},
			sizes : function(src) {
				return {};
			},
			init : function(target) {
				var images = target.querySelectorAll("img[data-simply-src]");
				for (var i=0; i<images.length; i++) {
					editor.responsiveImages.initImage(images[i]);
				}
			},
			errorHandler : function(evt) {
				if (!this.parentNode) {
					// We no longer exist in the dom;
					return;
				}
				if (this.errorHandled) {
					return;
				}
				var src = this.getAttribute("data-simply-src");

				this.removeAttribute("srcset");
				this.removeAttribute("sizes");
				this.setAttribute("src", src);

				// Bugfix for chrome - the image tag somehow
				// remembers that it is scaled, so now the
				// "natural" size of the image source is a
				// lot bigger than the image really is.
				// Cloning resolves this problem.

				// FIXME: Replacing the element causes a problem for databinding - need to rebind this.
				var clone = this.cloneNode();
				this.parentNode.insertBefore(clone, this.nextSibling); // insert the clone after! the current image to keep the selection;

				if (this.dataBinding) {
					this.dataBinding.rebind(clone);
				}
				this.parentNode.removeChild(this);
				editor.fireEvent("selectionchange", document);
			},
			isInEndpoint : function(imageSrc) {
				if (imageSrc) {
					var parser = document.createElement("A");
					parser.href = imageSrc;
					imageSrc = parser.href;
				}
				var imagesPath = this.getEndpoint();
				if (imagesPath && (imageSrc.indexOf(imagesPath) === 0)) {
					return true;
				}
				return false;
			},
			initImage : function(imgEl) {
				if (editor.responsiveImages.isInDocumentFragment(imgEl)) { // The image is still in the document fragment from the template, and not part of our document yet. This means we can't calculate any styles on it.
					if (!imgEl.simplyResponsiveImageTimer) {
						imgEl.simplyResponsiveImageTimer = window.setTimeout(function() {
							editor.responsiveImages.initImage(imgEl);
						}, 50);
					}
					return;
				}

				var imageSrc = imgEl.getAttribute("data-simply-src");
				if (!imageSrc) {
					return;
				}

				if (typeof imageSrc === "undefined") {
					return;
				}
				var srcSet = [];
				if (editor.responsiveImages.isInEndpoint(imageSrc)) {
					var sizes = editor.responsiveImages.sizes(imageSrc);
					for (var size in sizes) {
						srcSet.push(sizes[size] + " " + size);
					}
				}

				if (imgEl.dataBinding) {
					imgEl.dataBinding.pauseListeners(imgEl);
				}
				imgEl.removeAttribute("srcset");
				imgEl.removeAttribute("sizes");
				imgEl.removeAttribute("src");

				imgEl.removeEventListener("error", editor.responsiveImages.errorHandler);
				imgEl.addEventListener("error", editor.responsiveImages.errorHandler);

				var sizeRatio = editor.responsiveImages.getSizeRatio(imgEl);
				if (sizeRatio > 0) {
					imgEl.setAttribute("sizes", sizeRatio + "vw");
				}
				imgEl.setAttribute("srcset", srcSet.join(", "));
				imgEl.setAttribute("src", imageSrc);

				if (imgEl.dataBinding) {
					imgEl.dataBinding.resumeListeners(imgEl);
				}
				editor.fireEvent("selectionchange", document);
			},
			getSizeRatio : function(imgEl) {
				if (imgEl.dataBinding) {
					imgEl.dataBinding.pauseListeners(imgEl);
				}
				var storedAlt = imgEl.getAttribute("alt");
				var storedSrc = imgEl.getAttribute("src");

				imgEl.setAttribute("alt", "");
				imgEl.src = "data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7"; // transparent 1x1 gif, this forces a redraw of the image thus recalculating its width;
				var imageWidth = imgEl.width;
				if (storedSrc) {
					imgEl.setAttribute("src", storedSrc);
					imageWidth = imgEl.width;
				} else {
					imgEl.removeAttribute("src");
					if (imageWidth == 1) {
						// We didn't have a source to start with and the calculated width is 1 pixel
						// from the transparent 1x1. This means the width is not resized by the image
						// tag or css;
						imageWidth = 0;
					}
				}

				if (storedAlt) {
					imgEl.setAttribute("alt", storedAlt);
				}
				if (imgEl.dataBinding) {
					imgEl.dataBinding.resumeListeners(imgEl);
				}

				if (imgEl.simplyComputedWidth || imageWidth === 0) {
					imgEl.simplyComputedWidth = true;
					var computed = getComputedStyle(imgEl);

					if (computed.maxWidth) {
						if (computed.maxWidth.indexOf("%") != -1) {
							imageWidth = parseFloat(computed.maxWidth) / 100.0;
							var offsetParent = imgEl.offsetParent ? imgEl.offsetParent : imgEl.parentNode;
							imageWidth = offsetParent ? offsetParent.offsetWidth * imageWidth : 0;
						}
						if (computed.maxWidth.indexOf("px") != -1) {
							imageWidth = parseInt(computed.maxWidth);
						}
					}
				}

				var sizeRatio = parseInt(Math.ceil(100 * imageWidth / window.innerWidth));
				return sizeRatio;
			},
			resizeHandler : function() {
				var images = document.querySelectorAll("img[data-simply-src][sizes]");
				for (var i=0; i<images.length; i++) {
					var sizeRatio = editor.responsiveImages.getSizeRatio(images[i]);
					if (sizeRatio > 0) {
						images[i].setAttribute("sizes", sizeRatio + "vw");
					}
				}
			},
			isInDocumentFragment : function(el) {
				var parent = el.parentNode;
				while (parent) {
					if (parent.nodeType === document.DOCUMENT_FRAGMENT_NODE && parent != editor.toolbarsContainer) {
						return true;
					}
					parent = parent.parentNode;
				}
				return false;
			}
		}
	};

	var storage;
	if (scriptEl.getAttribute("data-simply-storage") == "none") {
		var returnTrue = function() {
			return true;
		};
		storage = {
			init : function(endpoint) {
				return {
					init : returnTrue,
					save : returnTrue,
					load : function(callback) {
						callback("{}");
					},
					connect: returnTrue,
					disconnect: returnTrue
				};
			}
		};
	} else {
		storage = {
			getType : function(endpoint) {
				if (document.querySelector("[data-simply-storage]")) {
					return document.querySelector("[data-simply-storage]").getAttribute("data-simply-storage");
				}
				if (endpoint === null) {
					endpoint = document.location.href;
				}
				if (endpoint.indexOf("/ariadne/loader.php/") !== -1) {
					return "ariadne";
				} else if (endpoint.indexOf("github.io") !== -1) {
					return "github";
				} else if (endpoint.indexOf("github.com") !== -1) {
					return "github";
				}
				return "default";
			},
			init : function(endpoint) {
				var result;

				var storageType = storage.getType(endpoint);

				if (storage[storageType]) {
					result = storage[storageType];
				} else if (window[storageType]) {
					result = window[storageType];
				} else {
					console.log("Warning: custom storage (" + storageType + ") not found");
				}

				if (!result.escape) {
					result.escape = storage.default.escape;
				}

				if (typeof result.init === "function") {
					result.init(endpoint);
				}
				return result;
			},
			ariadne : {
				init : function(endpoint) {
					if (endpoint === null) {
						endpoint = location.origin + "/";
					}
					this.url = endpoint;
					this.list = storage.default.list;
					this.sitemap = storage.default.sitemap;
					this.listSitemap = storage.default.listSitemap;
					this.disconnect = storage.default.disconnect;
					this.escape = storage.default.escape;
					this.page = storage.default.page;

					this.endpoint = endpoint;
					this.dataEndpoint = endpoint + "data.json";
					this.file = storage.default.file;

					if (editor.responsiveImages) {
						if (
							editor.settings['simply-image'] &&
							editor.settings['simply-image'].responsive
						) {
							if (typeof editor.settings['simply-image'].responsive.sizes === "function") {
								editor.responsiveImages.sizes = editor.settings['simply-image'].responsive.sizes;
							} else if (typeof editor.settings['simply-image'].responsive.sizes === "object") {
								editor.responsiveImages.sizes = (function(sizes) {
									return function(src) {
										var result = {};
										var info = src.split(".");
										var extension = info.pop().toLowerCase();
										if (extension === "jpg" || extension === "jpeg" || extension === "png") {
											for (var i=0; i<sizes.length; i++) {
												result[sizes[i] + "w"] = info.join(".") + "-simply-scaled-" + sizes[i] + "." + extension;
											}
										}
										return result;
									};
								}(editor.settings['simply-image'].responsive.sizes));
							}
						} else {
							editor.responsiveImages.sizes = function(src) {
								if (!(src.match(/\.(jpg|jpeg|png)$/i))) {
									return {};
								}

								return {
									"1200w" : src + "?size=1200",
									"800w" : src + "?size=800",
									"640w" : src + "?size=640",
									"480w" : src + "?size=480",
									"320w" : src + "?size=320",
									"160w" : src + "?size=160",
									"80w" : src + "?size=80"
								};
							};
						}

						window.addEventListener("resize", editor.responsiveImages.resizeHandler);
					}
				},
				save : function(data, callback) {
					return editor.storage.file.save("data.json", data, callback);
				},
				load : function(callback) {
					var http = new XMLHttpRequest();
					var url = editor.storage.dataEndpoint;
					if (editor.profile == "dev") {
						url += "?t=" + (new Date().getTime());
					}

					http.open("GET", url, true);
					http.onreadystatechange = function() {//Call a function when the state changes.
						if(http.readyState == 4) {
							if ((http.status > 199) && (http.status < 300) && http.responseText.length) { // accept any 2xx http status as 'OK';
								if (http.responseText === "") {
									console.log("Warning: data file found, but empty");
									return callback("{}");
								}
								callback(http.responseText.replace(/data-vedor/g, "data-simply"));
							} else {
								console.log("Could not load data, starting empty.");
								callback("{}");
							}
						}
					};
					http.send();
				},
				connect : function(callback) {
					var url = editor.storage.url + "login";
					var http = new XMLHttpRequest();
					http.open("POST", url, true);
					http.send();
					if (typeof callback === "function") {
						callback();
					}
					return true;
				}
			},
			beaker : {
				init : function(endpoint) {
					this.endpoint = endpoint;
					this.dataEndpoint = endpoint + "data.json";
					if (this.endpoint.indexOf("dat://") === 0 && window.DatArchive) {
						this.archive = new DatArchive(this.endpoint);
						this.archive.readFile("dat.json").then(function(data) {
							try {
								editor.storage.meta = JSON.parse(data);
								if (!editor.storage.meta.web_root) {
									editor.storage.meta.web_root = "/";
								}
								if (!editor.storage.meta.web_root.match(/\/$/)) {
									editor.storage.meta.web_root += "/";
								}
							} catch (e) {
								console.log("Warning: could not parse archive metadata (dat.json)");
							}
						});
					}
					this.load = storage.default.load;
					this.list = storage.default.list;
					this.sitemap = storage.default.sitemap;
					this.page = storage.default.page;
					this.listSitemap = storage.default.listSitemap;

					if (editor.responsiveImages) {
						if (
							editor.settings['simply-image'] &&
							editor.settings['simply-image'].responsive
						) {
							if (typeof editor.settings['simply-image'].responsive.sizes === "function") {
								editor.responsiveImages.sizes = editor.settings['simply-image'].responsive.sizes;
							} else if (typeof editor.settings['simply-image'].responsive.sizes === "object") {
								editor.responsiveImages.sizes = (function(sizes) {
									return function(src) {
										var result = {};
										var info = src.split(".");
										var extension = info.pop().toLowerCase();
										if (extension === "jpg" || extension === "jpeg" || extension === "png") {
											for (var i=0; i<sizes.length; i++) {
												result[sizes[i] + "w"] = info.join(".") + "-simply-scaled-" + sizes[i] + "." + extension;
											}
										}
										return result;
									};
								}(editor.settings['simply-image'].responsive.sizes));
							}
						}
						window.addEventListener("resize", editor.responsiveImages.resizeHandler);
					}
				},
				connect : function(callback) {
					callback();
				},
				save: function(data,callback) {
					editor.storage.file.save(this.dataEndpoint, data, callback);
				},
				saveTemplate : function(pageTemplate, callback) {
					var dataPath = location.pathname.split(/\//, 3)[2];
					if (dataPath.match(/\/$/)) {
						dataPath += "index.html";
					}

					editor.storage.archive.readFile(editor.storage.meta.web_root + pageTemplate).then(function(result) {
						if (result) {
							editor.storage.file.save(dataPath, result, callback);
						}
					});
				},
				file : {
					save : function(path, data, callback) {
						if (path.indexOf("dat://") === 0 ) {
							path = path.replace("dat://" + document.location.host + "/", '');
						}
						if (!editor.storage.archive) {
							callback({
								error : true,
								message : "No connection to dat archive (are you on https?)"
							});
							console.log("Warning: no connection to dat archive (are you on https?)");
							return;
						}
						editor.storage.archive.getInfo().then(function(info) {
							if (!info.isOwner) {
								callback({
									error : true,
									message : "Not the owner."
								});
								console.log("Warning: Save failed because we are not owner for this archive.");
								return;
							}

							var executeSave = function(path, data) {
								createDirectories(path)
								.then(function() {
									if (path.match(/\/$/)) {
										// path points to a directory;
										callback({});
									} else {
										editor.storage.archive.writeFile(editor.storage.meta.web_root + path, data).then(function() {
											editor.storage.archive.commit().then(function() {
												var saveResult = {path : path, response: "Saved."};
												callback(saveResult);
											});
										});
									}
								});
							};
							var createDirectory = function(path, callback) {
								return new Promise(function(resolve, reject) {
									path = path.replace(/^\/\//, "/");
									path = path.replace(/\/$/, "");
									editor.storage.archive.readdir(path).then(null, function () {
										editor.storage.archive.mkdir(path).then(function() {
											editor.storage.archive.commit().then(function() {
												resolve('created');
											});
										});
									});
								});
							};
							var createDirectories = function(path, callback) {
								return new Promise(function(resolve, reject) {
									var parts = path.split("/");
									if (!path.match(/\/$/)) {
										parts.pop(); // last part is the filename
									}
									var dirToCreate = '/';

									var promises = [];

									for (var i=0; i<parts.length; i++) {
										if (parts[i] !== "") {
											dirToCreate += parts[i] + "/";
										}
										if (dirToCreate != "/") {
											promises.push(createDirectory(editor.storage.meta.web_root + dirToCreate));
										}
									}
									Promise.all(promises).then(function() {
										resolve('created');
									});
								});
							};
							if (data instanceof File) {
								var fileReader = new FileReader();
								fileReader.onload = function(evt) {
									executeSave(path, this.result);
								};
								fileReader.readAsArrayBuffer(data);
							} else {
								executeSave(path, data);
							}
						});
					},
					delete : function(path, callback) {
						if (path.match(/\/$/)) {
							// path points to a directory;
							editor.storage.archive.rmdir(editor.storage.meta.web_root + path, {recursive: true}).then(function() {
								editor.storage.archive.commit().then(function() {
									callback();
								});
							});
						} else {
							editor.storage.archive.unlink(editor.storage.meta.web_root + path).then(function() {
								editor.storage.archive.commit().then(function() {
									callback();
								});
							});
						}
					}
				}
			},
			beakerBeta : {
				init : function(endpoint) {
					this.endpoint = endpoint;
					this.dataEndpoint = endpoint + "data.json";
					if (this.endpoint.indexOf("hyper://") === 0 && window.beaker) {
						this.hyperdrive = beaker.hyperdrive.drive(this.endpoint);
						this.hyperdrive.getInfo().then(function(meta) {
							editor.storage.meta = meta;
							editor.storage.meta.web_root = "/";
						});
					}
					this.load = storage.default.load;
					this.list = storage.default.list;
					this.sitemap = storage.default.sitemap;
					this.page = storage.default.page;
					this.listSitemap = storage.default.listSitemap;

					if (editor.responsiveImages) {
						if (
							editor.settings['simply-image'] &&
							editor.settings['simply-image'].responsive
						) {
							if (typeof editor.settings['simply-image'].responsive.sizes === "function") {
								editor.responsiveImages.sizes = editor.settings['simply-image'].responsive.sizes;
							} else if (typeof editor.settings['simply-image'].responsive.sizes === "object") {
								editor.responsiveImages.sizes = (function(sizes) {
									return function(src) {
										var result = {};
										var info = src.split(".");
										var extension = info.pop().toLowerCase();
										if (extension === "jpg" || extension === "jpeg" || extension === "png") {
											for (var i=0; i<sizes.length; i++) {
												result[sizes[i] + "w"] = info.join(".") + "-simply-scaled-" + sizes[i] + "." + extension;
											}
										}
										return result;
									};
								}(editor.settings['simply-image'].responsive.sizes));
							}
						}
						window.addEventListener("resize", editor.responsiveImages.resizeHandler);
					}
				},
				connect : function(callback) {
					callback();
				},
				save: function(data,callback) {
					editor.storage.file.save(this.dataEndpoint, data, callback);
				},
				saveTemplate : function(pageTemplate, callback) {
					var dataPath = location.pathname.split(/\//, 3)[2];
					if (dataPath.match(/\/$/)) {
						dataPath += "index.html";
					}

					editor.storage.hyperdrive.readFile(editor.storage.meta.web_root + pageTemplate).then(function(result) {
						if (result) {
							editor.storage.file.save(dataPath, result, callback);
						}
					});
				},
				file : {
					save : function(path, data, callback) {
						if (path.indexOf("hyper://") === 0 ) {
							path = path.replace("hyper://" + document.location.host + "/", '');
						}
						if (!editor.storage.hyperdrive) {
							callback({
								error : true,
								message : "No connection to hyperdrive (are you on https?)"
							});
							console.log("Warning: no connection to hyperdrive (are you on https?)");
							return;
						}
						editor.storage.hyperdrive.getInfo().then(function(info) {
							if (!info.writable) {
								callback({
									error : true,
									message : "Not writable."
								});
								console.log("Warning: Save failed the hyperdrive is not writable.");
								return;
							}

							var executeSave = function(path, data) {
								createDirectories(path)
								.then(function() {
									if (path.match(/\/$/)) {
										// path points to a directory;
										callback({});
									} else {
										editor.storage.hyperdrive.writeFile(editor.storage.meta.web_root + path, data).then(function() {
											var saveResult = {path : path, response: "Saved."};
											callback(saveResult);
										});
									}
								});
							};
							var createDirectory = function(path, callback) {
								return new Promise(function(resolve, reject) {
									path = path.replace(/^\/\//, "/");
									path = path.replace(/\/$/, "");
									editor.storage.hyperdrive.readdir(path).then(null, function () {
										editor.storage.hyperdrive.mkdir(path).then(function() {
											editor.storage.hyperdrive.commit().then(function() {
												resolve('created');
											});
										});
									});
								});
							};
							var createDirectories = function(path, callback) {
								return new Promise(function(resolve, reject) {
									var parts = path.split("/");
									if (!path.match(/\/$/)) {
										parts.pop(); // last part is the filename
									}
									var dirToCreate = '/';

									var promises = [];

									for (var i=0; i<parts.length; i++) {
										if (parts[i] !== "") {
											dirToCreate += parts[i] + "/";
										}
										if (dirToCreate != "/") {
											promises.push(createDirectory(editor.storage.meta.web_root + dirToCreate));
										}
									}
									Promise.all(promises).then(function() {
										resolve('created');
									});
								});
							};
							if (data instanceof File) {
								var fileReader = new FileReader();
								fileReader.onload = function(evt) {
									executeSave(path, this.result);
								};
								fileReader.readAsArrayBuffer(data);
							} else {
								executeSave(path, data);
							}
						});
					},
					delete : function(path, callback) {
						if (path.match(/\/$/)) {
							// path points to a directory;
							editor.storage.hyperdrive.rmdir(editor.storage.meta.web_root + path, {recursive: true}).then(function() {
								callback();
							});
						} else {
							editor.storage.hyperdrive.unlink(editor.storage.meta.web_root + path).then(function() {
								callback();
							});
						}
					}
				}
			},
			github : {
				repoName : null,
				repoUser : null,
				repoBranch : "gh-pages",
				dataFile : "data.json",
				getRepoInfo : function(endpoint) {
					var result = {};
					var parser = document.createElement('a');
					parser.href = endpoint;

					var pathInfo;
					pathInfo = parser.pathname.split("/");
					if (parser.pathname.indexOf("/") === 0) {
						pathInfo.shift();
					}

					if (parser.hostname == "github.com") {
						result.repoUser = pathInfo.shift();
						result.repoName =  pathInfo.shift();
						result.repoBranch = "master";
					} else {
						//github.io;
						result.repoUser = parser.hostname.split(".")[0];
						result.repoName = pathInfo.shift();
						result.repoBranch = "gh-pages";
					}

					if (document.querySelector("[data-simply-repo-branch]")) {
						result.repoBranch = document.querySelector("[data-simply-repo-branch]").getAttribute("data-simply-repo-branch");
					}
					if (document.querySelector("[data-simply-repo-name]")) {
						result.repoName = document.querySelector("[data-simply-repo-name]").getAttribute("data-simply-repo-name");
					}
					if (document.querySelector("[data-simply-repo-user]")) {
						result.repoUser = document.querySelector("[data-simply-repo-user]").getAttribute("data-simply-repo-user");
					}

					var repoPath = pathInfo.join("/");
					repoPath = repoPath.replace(/\/$/, '');

					result.repoPath = repoPath;
					return result;
				},
				checkJail : function(url) {
					var repo1 = this.getRepoInfo(url);
					var repo2 = this.getRepoInfo(this.endpoint);

					if (
						(repo1.repoUser == repo2.repoUser) && 
						(repo1.repoName == repo2.repoName) &&
						(repo1.repoBranch == repo2.repoBranch)
					) {
						return true;
					}
					return false;
				},
				init : function(endpoint) {
					if (endpoint === null) {
						endpoint = document.location.href.replace(document.location.hash, "");
					}
					var script = document.createElement("SCRIPT");
					script.src = editor.baseURLClean + "github.js";
					document.head.appendChild(script);

					var repoInfo = this.getRepoInfo(endpoint);
					this.repoUser = repoInfo.repoUser;
					this.repoName = repoInfo.repoName;
					this.repoBranch = repoInfo.repoBranch;

					this.endpoint = endpoint;
					this.dataFile = "data.json";
					this.dataEndpoint = endpoint + "data.json";

					this.sitemap = storage.default.sitemap;
					this.listSitemap = storage.default.listSitemap;
					this.page = storage.default.page;
					this.escape = storage.default.escape;

					if (editor.responsiveImages) {
						if (
							editor.settings['simply-image'] &&
							editor.settings['simply-image'].responsive
						) {
							if (typeof editor.settings['simply-image'].responsive.sizes === "function") {
								editor.responsiveImages.sizes = editor.settings['simply-image'].responsive.sizes;
							} else if (typeof editor.settings['simply-image'].responsive.sizes === "object") {
								editor.responsiveImages.sizes = (function(sizes) {
									return function(src) {
										var result = {};
										var info = src.split(".");
										var extension = info.pop().toLowerCase();
										if (extension === "jpg" || extension === "jpeg" || extension === "png") {
											for (var i=0; i<sizes.length; i++) {
												result[sizes[i] + "w"] = info.join(".") + "-simply-scaled-" + sizes[i] + "." + extension;
											}
										}
										return result;
									};
								}(editor.settings['simply-image'].responsive.sizes));
							}
						}
						window.addEventListener("resize", editor.responsiveImages.resizeHandler);
					}
				},
				connect : function(callback) {
					if (typeof Github === "undefined") {
						return false;
					}

					if (!editor.storage.key) {
						editor.storage.key = localStorage.storageKey;
					}
					if (!editor.storage.key) {
						editor.storage.key = prompt("Please enter your authentication key");
					}

					if (editor.storage.validateKey(editor.storage.key)) {
						if (!this.repo) {
							localStorage.storageKey = editor.storage.key;
							this.github = new Github({
								token: editor.storage.key,
								auth: "oauth"
							});
							this.repo = this.github.getRepo(this.repoUser, this.repoName);
						}
						if (typeof callback === "function") {
							callback();
						}
						return true;
					} else {
						return editor.storage.connect(callback);
					}
				},
				disconnect : function(callback) {
					delete this.repo;
					delete localStorage.storageKey;
					if (typeof callback === "function") {
						callback();
					}
					return true;
				},
				validateKey : function(key) {
					return true;
				},
				file : {
					save : function(path, data, callback) {
						if (path.match(/\/$/)) {
							// github will create directories as needed.
							var saveResult = {path : path, response: "Saved."};
							return callback(saveResult);
						}

						var saveCallback = function(err) {
							if (err === null) {
								var saveResult = {path : path, response: "Saved."};
								return callback(saveResult);
							}

							if (err.error == 401) {
								return callback({message : "Authorization failed.", error: true});
							}
							return callback({message : "SAVE FAILED: Could not store.", error: true});
						};

						var executeSave = function(path, data) {
							editor.storage.repo.write(editor.storage.repoBranch, path, data, "Simply edit changes on " + new Date().toUTCString(), saveCallback);
						};
						if (data instanceof File) {
							var fileReader = new FileReader();
							fileReader.onload = function(evt) {
								executeSave(path, this.result);
							};
							fileReader.readAsBinaryString(data);
						} else {
							executeSave(path, data);
						}
					},
					delete : function(path, callback) {
						editor.storage.repo.delete(editor.storage.repoBranch, path, callback);
					}
				},
				save : function(data, callback) {
					return editor.storage.file.save("data.json", data, callback);
				},
				load : function(callback) {
					var http = new XMLHttpRequest();
					var url = "https://raw.githubusercontent.com/" + this.repoUser + "/" + this.repoName + "/" + this.repoBranch + "/" + this.dataFile;
					if (editor.profile == "dev") {
						url += "?t=" + (new Date().getTime());
					}
					http.open("GET", url, true);
					http.onreadystatechange = function() {//Call a function when the state changes.
						if(http.readyState == 4) {
							if ((http.status > 199) && (http.status < 300)) { // accept any 2xx http status as 'OK';
								if (http.responseText === "") {
									console.log("Warning: data file found, but empty");
									return callback("{}");
								}
								callback(http.responseText);
							} else {
								console.log("No data found, starting with empty dataset");
								callback("{}");
							}
						}
					};
					http.send();
				},
				saveTemplate : function(pageTemplate, callback) {
					var dataPath = location.pathname.split(/\//, 3)[2];
					if (dataPath.match(/\/$/)) {
						dataPath += "index.html";
					}

					var repo = this.repo;
					repo.read(this.repoBranch, pageTemplate, function(err, data) {
						if (data) {
							repo.write(this.repoBranch, dataPath, data, pageTemplate + " (copy)", callback);
						}
					});
				},
				list : function(url, callback) {
					if (url.indexOf(editor.storage.dataEndpoint) === 0) {
						return this.listSitemap(url, callback);
					}

					var repoInfo = this.getRepoInfo(url);

					var repoUser = repoInfo.repoUser;
					var repoName = repoInfo.repoName;
					var repoBranch = repoInfo.repoBranch;
					var repoPath = repoInfo.repoPath;

					var github = new Github({});
					var repo = github.getRepo(repoUser, repoName);
					repo.read(repoBranch, repoPath, function(err, data) {
						var result = {
							images : [],
							folders : [],
							files : []
						};

						if (data) {
							data = JSON.parse(data);
							for (var i=0; i<data.length; i++) {
								if (data[i].type == "file") {
									var fileData = {
										url : url + data[i].name,
										src : url + data[i].name,
										name : data[i].name // data[i].download_url
									};
									if (url === editor.storage.endpoint && data[i].name === "data.json") {
										fileData.name = "My pages";
										result.folders.push(fileData);
									} else {
										result.files.push(fileData);
										if (fileData.url.match(/(jpg|jpeg|gif|png|bmp|tif|svg)$/i)) {
											result.images.push(fileData);
										}
									}
								} else if (data[i].type == "dir") {
									result.folders.push({
										url : editor.storage.endpoint + data[i].path + "/",
										name : data[i].name
									});
								}
							}
							callback(result);
						} else {
							// Empty (non-existant) directory - return the empty resultset, github will create the dir automatically when we save things to it.");
							callback(result);
						}
					});
				}
			},
			default : {
				init : function(endpoint) {
					if (endpoint === null) {
						endpoint = location.origin + "/";
					}
					this.url = endpoint;
					this.endpoint = endpoint;
					this.dataPath = "data/data.json";
					this.dataEndpoint = this.url + this.dataPath;
					if (document.querySelector("[data-storage-get-post-only]")) {
						this.getPostOnly = true;
					}

					if (editor.responsiveImages) {
						if (
							editor.settings['simply-image'] &&
							editor.settings['simply-image'].responsive
						) {
							if (typeof editor.settings['simply-image'].responsive.sizes === "function") {
								editor.responsiveImages.sizes = editor.settings['simply-image'].responsive.sizes;
							} else if (typeof editor.settings['simply-image'].responsive.sizes === "object") {
								editor.responsiveImages.sizes = (function(sizes) {
									return function(src) {
										var result = {};
										var info = src.split(".");
										var extension = info.pop().toLowerCase();
										if (extension === "jpg" || extension === "jpeg" || extension === "png") {
											for (var i=0; i<sizes.length; i++) {
												result[sizes[i] + "w"] = info.join(".") + "-simply-scaled-" + sizes[i] + "." + extension;
											}
										}
										return result;
									};
								}(editor.settings['simply-image'].responsive.sizes));
							}
						}
						window.addEventListener("resize", editor.responsiveImages.resizeHandler);
					}
				},
				escape : function(path) {
					return path.replace(/[^A-Za-z0-9_\.-]/g, "-");
				},
				file : {
					save : function(path, data, callback) {
						var http = new XMLHttpRequest();
						var url = editor.storage.url + path;
						if (editor.storage.getPostOnly) {
							url += "?_method=PUT";
							http.open("POST", url, true);
						} else {
							http.open("PUT", url, true);
						}
						http.withCredentials = true;

						http.onreadystatechange = function() {//Call a function when the state changes.
							if(http.readyState == 4) {
								var saveResult = {};
								if ((http.status > 199) && (http.status < 300)) { // accept any 2xx http status as 'OK';
									saveResult = {path : path, response: http.responseText};
								} else {
									saveResult = {path : path, message : "SAVE FAILED: Could not store.", error: true, response: http.responseText};
								}
								var saveEvent = editor.fireEvent("simply-storage-file-saved", document, saveResult);
								if (!saveEvent.defaultPrevented) {
									callback(saveEvent.data);
								}
							}
						};
						http.upload.onprogress = function (event) {
							if (event.lengthComputable) {
								var complete = (event.loaded / event.total * 100 | 0);
								var progress = document.querySelector("progress[data-simply-progress='" + editor.storage.escape(path) + "']");
								if (progress) {
									progress.value = progress.innerHTML = complete;
								}
							}
						};

						http.send(data);
					},
					delete : function(path, callback) {
						var http = new XMLHttpRequest();
						var url = editor.storage.url + path;

						if (editor.storage.getPostOnly) {
							url += "?_method=DELETE";
							http.open("POST", url, true);
						} else {
							http.open("DELETE", url, true);
						}
						http.withCredentials = true;

						http.onreadystatechange = function() {//Call a function when the state changes.
							if(http.readyState == 4) {
								var deleteResult = {};
								if ((http.status > 199) && (http.status < 300)) { // accept any 2xx http status as 'OK';
									deleteResult = {path : path, response: http.responseText};
								} else {
									deleteResult = {path : path, message : "DELETE FAILED: Could not delete.", error: true, response: http.responseText};
								}
								var deleteEvent = editor.fireEvent("simply-storage-file-deleted", document, deleteResult);
								if (!deleteEvent.defaultPrevented) {
									callback(deleteEvent.data);
								}
							}
						};

						http.send();
					}
				},
				save : function(data, callback) {
					return editor.storage.file.save(this.dataPath, data, callback);
				},
				load : function(callback) {
					var http = new XMLHttpRequest();
					var url = editor.storage.dataEndpoint;
					if (editor.profile == "dev") {
						url += "?t=" + (new Date().getTime());
					}
					http.open("GET", url, true);
					http.onreadystatechange = function() {//Call a function when the state changes.
						if(http.readyState == 4) {
							if ((http.status > 199) && (http.status < 300)) { // accept any 2xx http status as 'OK';
								if (http.responseText === "") {
									console.log("Warning: data file found, but empty");
									return callback("{}");
								}
								callback(http.responseText.replace(/\0+$/, ''));
							} else {
								callback("{}");
								console.log("Warning: no data found. Starting with empty set");
							}
						}
					};
					http.send();
				},
				connect : function(callback) {
					var http = new XMLHttpRequest();
					var url = editor.storage.url + "login";
					http.open("POST", url, true);
					http.send();
					if (typeof callback === "function") {
						callback();
					}
					return true;
				},
				disconnect : function(callback) {
					delete editor.storage.key;
					delete localStorage.storageKey;

					var http = new XMLHttpRequest();
					var url = editor.storage.url + "logout";
					http.open("GET", url, true, "logout", (new Date()).getTime().toString());
					http.setRequestHeader("Authorization", "Basic ABCDEF");

					http.onreadystatechange = function() {//Call a function when the state changes.
						if(http.readyState == 4 && ((http.status > 399) && (http.status < 500)) ) {
							if (typeof callback === "function") {
								callback();
							}
						}
					};
					http.send();
				},
				page : {
					save : function(url) {
						history.pushState(null, null, url + "#simply-edit");

						document.body.innerHTML = editor.data.originalBody.innerHTML;
						document.body.removeAttribute("data-simply-edit");

						editor.data.load();
						var openTemplateDialog = function() {
							if (editor.actions['simply-template']) {
								if (!document.getElementById("simply-template")) {
									window.setTimeout(openTemplateDialog, 200);
									return;
								}
								editor.actions['simply-template']();
							} else {
								alert("This page does not exist yet. Save it to create it!");
							}
						};
						openTemplateDialog();
					}
				},
				sitemap : function() {
					var output = {
						children : {},
						name : 'Sitemap'
					};
					for (var i in editor.currentData) {
						var chain = i.split("/");
						chain.shift();
						var lastItem = chain.pop();
						if (lastItem !== "") {
							chain.push(lastItem);
						} else {
							var item = chain.pop();
							if (typeof item === "undefined") {
								item = '';
							}
							chain.push(item + "/");
						}

						var currentNode = output.children;
						var prevNode;
						for (var j = 0; j < chain.length; j++) {
							var wantedNode = chain[j];
							var lastNode = currentNode;
							for (var k in currentNode) {
								if (currentNode[k].name == wantedNode) {
									currentNode = currentNode[k].children;
									break;
								}
							}
							// If we couldn't find an item in this list of children
							// that has the right name, create one:
							if (lastNode == currentNode) {
								currentNode[wantedNode] = {
									name : wantedNode,
									children : {}
								};
								currentNode = currentNode[wantedNode].children;
							}
						}
					}
					return output;
				},
				listSitemap : function(url, callback) {
					if (url.indexOf(editor.storage.dataEndpoint) === 0) {
						var subpath = url.replace(editor.storage.dataEndpoint, "");
						var sitemap = editor.storage.sitemap();
						var result = {
							folders : [],
							files : []
						};
						if (subpath !== "") {
							var pathicles = subpath.split("/");
							pathicles.shift();
							for (var i=0; i<pathicles.length; i++) {
								if (sitemap.children[pathicles[i]]) {
									sitemap = sitemap.children[pathicles[i]];
								} else {
									sitemap = {};
									break;
								}
							}
							result.folders.push({
								url : url.replace(/\/[^\/]+$/, ''),
								name : '..'
							});
						} else {
							result.folders.push({
								url : editor.storage.endpoint,
								name : '..'
							});
						}

						for (var j in sitemap.children) {
							if (j=="/") {
								result.files.push({
									url : url + "/",
									name : "Home"
								});
							}

							if (Object.keys(sitemap.children[j].children).length) {
								result.folders.push({
									url : url + "/" + j,
									name : j + "/"
								});
							} else {
								if (j != "/") {
									result.files.push({
										url : url + "/" + j,
										name : j.replace(/\/$/, '')
									});

									if (Object.keys(editor.currentData[(url + "/" + j).replace(editor.storage.dataEndpoint, "")]).length === 0) {
										result.folders.push({
											url : url + "/" + j.replace(/\/$/, ''),
											name : j
										});
									}
								}
							}
						}

						return callback(result);
					}
				},
				list : function(url, callback) {
					if (url.indexOf(editor.storage.dataEndpoint) === 0) {
						return this.listSitemap(url, callback);
					}
					if (url == editor.storage.endpoint) {
						var result = {
							images : [],
							folders : [],
							files : []
						};
						result.folders.push({url : editor.storage.dataEndpoint, name : 'My pages'});
						var parser = document.createElement("A");

						if (document.querySelector("[data-simply-images]")) {
							var imagesEndpoint = document.querySelector("[data-simply-images]").getAttribute("data-simply-images");
							parser.href = imagesEndpoint;
							imagesEndpoint = parser.href;
							result.folders.push({url : imagesEndpoint, name : 'My images'});
						}
						if (document.querySelector("[data-simply-files]")) {
							var filesEndpoint = document.querySelector("[data-simply-files]").getAttribute("data-simply-files");
							parser.href = filesEndpoint;
							filesEndpoint = parser.href;
							result.folders.push({url : filesEndpoint, name : 'My files'});
						}
						return callback(result);
					}

					url += "?t=" + (new Date().getTime());
					var iframe = document.createElement("IFRAME");
					iframe.src = url;
					iframe.style.opacity = 0;
					iframe.style.position = "absolute";
					iframe.style.left = "-10000px";
					iframe.addEventListener("load", function() {
						var result = {
							images : [],
							folders : [],
							files : []
						};

						try {
							var images = iframe.contentDocument.body.querySelectorAll("a");
							for (var i=0; i<images.length; i++) {
								href = images[i].getAttribute("href");
								if (href.substring(0, 1) === "?") {
									continue;
								}

								var targetUrl = images[i].href;
								if (href.substring(href.length-1, href.length) === "/") {
									result.folders.push({url : targetUrl, name : images[i].innerHTML});
								} else {
									if (targetUrl === editor.storage.dataEndpoint) {
										result.folders.push({url : targetUrl, name: "My pages"});
									} else {
										result.files.push({url : targetUrl, name : images[i].innerHTML});
										if (targetUrl.match(/(jpg|jpeg|gif|png|bmp|tif|svg)$/i)) {
											result.images.push({url : targetUrl, name : images[i].innerHTML});
										}
									}
								}
							}

							document.body.removeChild(iframe);
						} catch(e) {
							console.log("The target endpoint could not be accessed.");
							console.log(e);
						}

						callback(result);
					});
					document.body.appendChild(iframe);
				}
			}
		};
	}

	editor.toolbars = {};
	editor.contextFilters = {};
	editor.plugins = {};
	editor.dataSources = {};
	editor.actions = {};
	editor.loadToolbar = function(url) {
		if (!editor.toolbar || (typeof muze === "undefined")) {
			// Main toolbar code isn't loaded yet;
			editor.editmode.toolbars.push(url);
		} else {
			editor.editmode.loadToolbarList([url]);
		}
	};

	editor.addToolbar = function(toolbar) {
		if (toolbar.filter) {
			editor.addContextFilter(toolbar.name, toolbar.filter);
		}
		for (var i in toolbar.actions) {
			editor.actions[i] = toolbar.actions[i];
		}
		editor.toolbars[toolbar.name] = toolbar;
		if (toolbar.init) {
			toolbar.init(editor.settings[toolbar.name]);
		}
	};

	editor.addDataSource = function(name, datasource) {
		editor.dataSources[name] = datasource;
	};

	editor.addContextFilter = function(name, filter) {
		if (!filter.context) {
			filter.context = name;
		}
		if (typeof editor.contextFilters[name] !== "undefined") {
			console.log("Warning: Context filter " + name + " is already defined.");
		}
		editor.contextFilters[name] = filter;
	};
	editor.addAction = function(name, action) {
		editor.actions[name] = action;
	};

	var preventDOMContentLoaded = function(event) {
		event.preventDefault();
		return false;
	};

	if ("addEventListener" in document) {
		document.addEventListener("DOMContentLoaded", preventDOMContentLoaded, true);
		window.addEventListener("load", preventDOMContentLoaded, true);
	}

	if (typeof jQuery !== "undefined") {
		if (typeof jQuery.holdReady === "function") {
			jQuery.holdReady(true);
		}
	}

	document.addEventListener("simply-content-loaded", function(evt) {
		if ("removeEventListener" in document) {
			document.removeEventListener("DOMContentLoaded", preventDOMContentLoaded, true);
			window.removeEventListener("load", preventDOMContentLoaded, true);
		}
			
		editor.fireEvent("DOMContentLoaded", document);
		window.setTimeout(function() {
			editor.fireEvent("load", window);
		}, 100);

		if (typeof jQuery !== "undefined") {
			if (typeof jQuery.holdReady === "function") {
				jQuery.holdReady(false);
			}
		}
	});

	// Add fake window.console for IE8/9
	if (!window.console) console = {log: function() {}};

	window.editor = editor;
	editor.storageConnectors = storage;

	editor.settings = {};
	// Find custom settings if they are set;
	var simplySettings = scriptEl.getAttribute("data-simply-settings");
	if (simplySettings) {
		var customSettings = window[simplySettings];
		if (customSettings) {
			editor.settings = customSettings;
		} else {
			console.log("Warning: data-simply-settings was set, but no settings were found. Starting without them...");
		}
	}

	var dataSources = scriptEl.getAttribute("data-simply-datasources");
	if (window[dataSources]) {
		editor.dataSources = window[dataSources];
	}
	if (!editor.settings.databind) {
		editor.settings.databind = {};
	}

	if (editor.settings.databind.resolve) {
		var savedResolver = editor.settings.databind.resolve;
		editor.settings.databind.resolve = function() {
			var args = {
				dataBinding : this,
				arguments : arguments
			};
			editor.fireEvent("simply-data-changed", document, args);
			savedResolver.apply(this, arguments);
		};
	} else {
		editor.settings.databind.resolve = function() {
			var args = {
				dataBinding : this,
				arguments : arguments
			};
			editor.fireEvent("simply-data-changed", document, args);
		};
	}

	var defaultToolbars = [
		editor.baseURL + "simply/toolbar.simply-basepack.html"
	//	editor.baseURL + "simply/plugin.simply-download.html"
	];

	if (typeof editor.settings.plugins === 'object') {
		for(var i=0; i<editor.settings.plugins.length; i++) {
			var toolbarUrl = editor.settings.plugins[i];
			if (toolbarUrl.indexOf("//") < 0) {
				toolbarUrl = editor.baseURL + "simply/" + toolbarUrl;
			}
			defaultToolbars.push(toolbarUrl);
		}
	}

	// Backwards compatibility for pre-0.50;
	editor.data.list = editor.list;
	editor.data.list.applyTemplates = editor.list.set;

	/*
		Two way databinding between a data object and DOM element(s).
		A databinding is attached to one data object. It can be bound to one or more elements.
		Changes in the element are resolved every x ms;
		Changes in the data are resolved to the element directly;
	
		config options:
			data: the data object to be used for databinding. Note that this is the 'outer' object, the databinding itself will be set on data[key];
			key: the key within the data object to be bound
			setter: a function that sets the data on the element. A simple example would take the provided value and set it as innerHTML.
			getter: a function that fetches the data from an element. Simple example would be "return target.innerHTML";
			mode: "list" of "field"; the only difference between the two is the listeners that are applied to the supplied element.
				"list" listens on attribute changes, node insertions and node removals.
				"field" listens on attribute changes, subtree modifications.
			parentKey: an additional pointer to where the data is bound without your datastructure; use this to keep track of nesting within your data.
			attributeFilter: a blacklist of attributes that should not trigger a change in data;
			resolve: a function that is called _after_ a change in data has been resolved. The arguments provided to the function are: dataBinding, key, value, oldValue
	
		Basic usage usage:
			var data = {
				"title" : "foo"
			};
	
			var dataBinding = new databinding({
				data : data,
				key : title,
				setter : function(value) {
					this.innerHTML = value;
				},
				getter: function() {
					return this.innerHTML;
				}
			});
	
	
			dataBinding.bind(document.getElementById('title'));
	
			console.log(data.title); // "foo"
			data.title = "Hello world"; // innerHTML for title is changed to 'Hello world';
			console.log(data.title); // "Hello world"
			document.getElementById('title').innerHTML = "Bar";
			console.log(data.title); // "Bar"
	*/
	
	dataBinding = function(config) {
		var data = config.data;
		var key = config.key;
		this.config = config;
		this.setter = config.setter;
		this.getter = config.getter;
		this.mode = config.mode;
		this.parentKey = config.parentKey ? config.parentKey : "";
	
		this.key = config.key;
		this.attributeFilter = config.attributeFilter;
		this.elements = [];
		var changeStack = [];
		var binding = this;
		var shadowValue;
		binding.resolveCounter = 0;
	
		var oldValue;
	
		if (!this.mode) {
			this.mode = "field";
		}
	
		if (Array.isArray(data[key])) {
			if (this.mode == "field") {
				console.log("Warning: databinding started in field mode but array-type data given; Switching to list mode.");
				console.log(key);
			}
			this.mode = "list";
			this.config.mode = "list";
		}
		if (!this.attributeFilter) {
			this.attributeFilter = [];
		}
	
		// If we already have a databinding on this data[key], re-use that one instead of creating a new one;
		if (data.hasOwnProperty("_bindings_") && data._bindings_[key]) {
			return data._bindings_[key];
		}
		var dereference = function(value) {
			if (typeof value==="undefined") {
				return value;
			}
			return JSON.parse(JSON.stringify(value));
		};
		var isEqual = function(value1, value2) {
			return JSON.stringify(value1) == JSON.stringify(value2);
		};
		this.setData = function(newdata) {
			data = newdata;
			initBindings(data, key);
		};
	
		var reconnectParentBindings = function(binding) {
			var parent;
	
			if (binding.config.data._parentBindings_) {
				parent = binding.config.data._parentBindings_[binding.key];
				while (parent && parent.get()[binding.key] == binding.get()) {
					binding = parent;
					parent = binding.config.data._parentBindings_? binding.config.data._parentBindings_[binding.key] : null;
					if (!parent) {
						if (binding.config.data._parentData_ && (binding.config.data._parentData_[binding.key] !== binding.get())) {
							binding.config.data._parentData_[binding.key] = binding.get();
						}
						for (var i in binding.config.data._parentBindings_) {
							parent = binding.config.data._parentBindings_[i];
							continue;
						}
					}
				}
			}
		};
	
		var setShadowValue = function(value) {
			var valueBindings;
			if (shadowValue && shadowValue._bindings_) {
				valueBindings = shadowValue._bindings_;
			}
	
			shadowValue = value;
			reconnectParentBindings(binding);
	
			if (valueBindings && (typeof shadowValue === "object")) {
				if (shadowValue && !shadowValue.hasOwnProperty("_bindings_")) {
					var bindings = {};
	
					Object.defineProperty(shadowValue, "_bindings_", {
						get : function() {
							return bindings;
						},
						set : function(value) {
							bindings[key] = binding;
						}
					});
				}
	
				var setRestoreTrigger = function(data, key, previousBinding) {
					var prevDescriptor = Object.getOwnPropertyDescriptor(previousBinding.config.data, key);
					var childTriggers = function(previousData) {
						return function(value) {
							if (typeof value === "undefined") {
								return;
							}
							if (previousData && previousData._bindings_) {
								for (var i in previousData._bindings_) {
									if (typeof value[i] === "undefined") {
										setRestoreTrigger(value, i, previousData._bindings_[i]);
										value._bindings_[i] = previousData._bindings_[i];
									} else {
										value._bindings_[i] = previousData._bindings_[i];
										value._bindings_[i].config.data = value;
										value._bindings_[i].set(value[i]);
									}
								}
							}
						};
					}(previousBinding.config.data[key]);
	
					previousBinding.config.data = data;
				//	binding.config.data = data;
	
					// binding.set(null);
					// delete data[key];
					var restoreBinding = function(value) {
						if (typeof value === "object" && !value.hasOwnProperty("_bindings_")) {
							var bindings = {};
	
							Object.defineProperty(value, "_bindings_", {
								get : function() {
									return bindings;
								},
								set : function(value) {
									bindings[key] = previousBinding;
								}
							});
						}
						childTriggers(value);
						data._bindings_[key].setData(data);
						data._bindings_[key].set(value);
						if (typeof prevDescriptor.get !== "function" && typeof prevDescriptor.set === "function") {
							prevDescriptor.set(value);
						}
					};
	
					Object.defineProperty(data, key, {
						set : restoreBinding,
						configurable : true
					});
				};
	
				for (var i in valueBindings) {
					if (typeof shadowValue[i] === "undefined") {
						if (typeof valueBindings[i].get() === "string") {
							valueBindings[i].set("");
						} else if (typeof valueBindings[i].get() === "object") {
							if (valueBindings[i].get() instanceof Array) {
								valueBindings[i].config.data[i] = [];
							} else {
								valueBindings[i].config.data[i] = {};
							}
						}
	
						setRestoreTrigger(shadowValue, i, valueBindings[i]);
					} else {
						valueBindings[i].set(shadowValue[i]);
						valueBindings[i].resolve(true);
					}
					shadowValue._bindings_[i] = valueBindings[i];
				}
			}
	
			if (typeof oldValue !== "undefined" && !isEqual(oldValue, shadowValue)) {
				binding.config.resolve.call(binding, key, dereference(shadowValue), dereference(oldValue));
			}
			//if (typeof shadowValue === "object") {
			//	shadowValue = dereference(shadowValue);
			//}
			monitorChildData(shadowValue);
		};
		var monitorChildData = function(data) {
			// Watch for changes in our child data, because these also need to register as changes in the databound data/elements;
			// This allows the use of simple data structures (1 key deep) as databound values and still resolve changes on a specific entry;
			var parentData = data;
	
			if (typeof data === "object") {
				var monitor = function(data, key) {
					if (!data.hasOwnProperty("_parentBindings_")) {
						var bindings = {};
	
						Object.defineProperty(data, "_parentBindings_", {
							get : function() {
								return bindings;
							},
							set : function(value) {
								bindings[key] = binding;
							}
						});
						Object.defineProperty(data, "_parentData_", {
							get : function() {
								return parentData;
							}
						});
					}
					data._parentBindings_[key] = binding;
	
					var myvalue = data[key];
	
					var renumber = function(key, value, parentBinding) {
						var oldparent, newparent;
						if (value && value._bindings_) {
							for (var subbinding in value._bindings_) {
								oldparent = value._bindings_[subbinding].parentKey;
								newparent = parentBinding.parentKey + parentBinding.key + "/" + key + "/";
								// console.log(oldparent + " => " + newparent);
								value._bindings_[subbinding].parentKey = newparent;
								if (value[subbinding] && value[subbinding].length) {
									for (var i=0; i<value[subbinding].length; i++) {
										renumber(i, value[subbinding][i], value._bindings_[subbinding]);
									}
								}
							}
						}
					};
	
					renumber(key, myvalue, binding);
	
					Object.defineProperty(data, key, {
						set : function(value) {
							myvalue = value;
							renumber(key, value, binding);
	
							if (parentData._bindings_ && parentData._bindings_[key]) {
								parentData._bindings_[key].set(value);
								parentData._bindings_[key].resolve();
							}
	
							// Marker is set by the array function, it will do the resolve after we're done.
							if (!binding.runningArrayFunction) {
								newValue = shadowValue;
								shadowValue = null;
								binding.set(newValue);
								binding.resolve();
							}
						},
						get : function() {
							if (parentData._bindings_ && parentData._bindings_[key]) {
								return parentData._bindings_[key].get();
							}
							return myvalue;
						}
					});
				};
	
				for (var key in data) {
					if (typeof data[key] !== "function") { // IE11 has a function 'includes' for arrays;
						monitor(data, key);
					}
				}
			}
	
			// Override basic array functions in the databound data, if it is an array;
			// Allows the use of basic array functions and still resolve changes.
			if (data instanceof Array) {
				overrideArrayFunction = function(name) {
					if (data.hasOwnProperty(name)) {
						return; // we already did this;
					}
					Object.defineProperty(data, name, {
						value : function() {
							binding.resolve(); // make sure the shadowValue is in sync with the latest state;
	
							// Add a marker so that array value set does not trigger resolving, we will resolve after we're done.
							binding.runningArrayFunction = true;
							var result = Array.prototype[name].apply(shadowValue, arguments);
							for (var i in shadowValue) {
								shadowValue[i] = shadowValue[i]; // this will force a renumber/reindex for the parentKeys;
							}
							binding.runningArrayFunction = false;
	
							for (var j=0; j<binding.elements.length; j++) {
								binding.bind(binding.elements[j]);
							}
	
							newValue = shadowValue;
							shadowValue = null;
							binding.set(newValue);
							binding.resolve(); // and apply our array change;
	
							return result;
						}
					});
				};
				overrideArrayFunction("pop");
				overrideArrayFunction("push");
				overrideArrayFunction("shift");
				overrideArrayFunction("unshift");
				overrideArrayFunction("splice");
			}
		};
		var resolverIsLooping = function() {
			// Check for resolve loops - 5 seems like a safe count. If we pass this point 5 times within the same stack execution, break the loop.
			binding.resolveCounter++;
			if (binding.resolveCounter > 5) {
				console.log("Warning: databinding resolve loop detected!");
				window.setTimeout(function() {
					binding.resolveCounter = 0;
				}, 300); // 300 is a guess; could be any other number. It needs to be long enough so that everyone can settle down before we start resolving again.
				return true;
			}
			return false;
		};
	
		var setElements = function() {
			if (binding.elementTimer) {
				window.clearTimeout(binding.elementTimer);
			}
			for (var i=0; i<binding.elements.length; i++) {
				if (
					// binding.mode == "list" || // if it is a list, we need to reset the values so that the bindings are setup properly.
					// FIXME: Always setting a list element will make a loop - find a better way to setup the bindings;
					(!isEqual(binding.elements[i].getter(), shadowValue))
				) {
					binding.pauseListeners(binding.elements[i]);
					binding.elements[i].setter(shadowValue);
					binding.resumeListeners(binding.elements[i]);
				}
				fireEvent(binding.elements[i], "elementresolved");
			}
			if (data._parentBindings_ && data._parentBindings_[key] && data._parentBindings_[key] !== binding) {
				data[key] = shadowValue; 
			}
			if (typeof binding.config.resolve === "function") {
				if (!isEqual(oldValue, shadowValue)) {
					oldValue = dereference(shadowValue);
				}
			}
			fireEvent(document, "resolved");
		};
	
		var initBindings = function(data, key) {
			if (typeof data != "object") {
				console.log("Attempted to bind on non-object data for " + key);
				return;
			}
	
			if (!data.hasOwnProperty("_bindings_")) {
				var bindings = {};
	
				Object.defineProperty(data, "_bindings_", {
					get : function() {
						return bindings;
					},
					set : function(value) {
						bindings[key] = binding;
					}
				});
			}
	
			setShadowValue(data[key]);
			oldValue = dereference(data[key]);
	
			data._bindings_[key] = binding;
			if (binding.mode == "list") {
				if (data[key] === null) {
					data[key] = [];
				}
			}
	
			Object.defineProperty(data, key, {
				set : function(value) {
					if (!isEqual(value, shadowValue)) {
						binding.set(value);
						binding.resolve(true);
					}
					if (data._parentBindings_ && data._parentBindings_[key]) {
						if (data._parentBindings_[key].get()[key] !== value) {
							data._parentBindings_[key].get()[key] = value;
							data._parentBindings_[key].resolve(true);
						}
					}
				},
				get : function() {
					return shadowValue;
				},
				enumerable: true
			});
		};
		var fireEvent = function(targetNode, eventName, detail) {
			var event = document.createEvent('CustomEvent');
			if (event && event.initCustomEvent) {
				event.initCustomEvent('databind:' + eventName, true, true, detail);
			} else {
				event = document.createEvent('Event');
				event.initEvent('databind:' + eventName, true, true);
				event.detail = detail;
			}
			return targetNode.dispatchEvent(event);
		};
		this.fireEvent = fireEvent;
	
		this.set = function (value) {
			changeStack.push(value);
			this.resolve();
		};
	
		this.get = function() {
			if (changeStack.length) {
				this.resolve();
			}
			return shadowValue;
		};
	
		this.resolve = function(instant) {
			if (!changeStack.length) {
				if (instant) {
					setElements();
				}
				return; // No changes to resolve;
			}
			var value = changeStack.pop(); // Only apply the last change;
			changeStack = [];
	
			if (isEqual(value, shadowValue)) {
				return; // The change is not actually a change, so no action needed;
			}
	
			if (resolverIsLooping()) {
				return; // The resolver is looping, yield to give everything time to settle down;
			}
	
			setShadowValue(value);		// Update the shadowValue to the new value;
	
			if (binding.config.data._simplyConverted) {
				// Update the reference in the parent to the new value as well;
				binding.config.data._simplyConvertedParent[binding.config.data._simplyConvertedParent.indexOf(binding.config.data)] = value;
			}
	
			if (instant) {
				setElements();
			} else {
				if (binding.elementTimer) {
					window.clearTimeout(binding.elementTimer);
				}
				binding.elementTimer = window.setTimeout(function() {
					setElements();	// Set the new value in all the databound elements;
				}, 100);
			}
	
			binding.resolveCounter--;
		};
	
		this.bind = function(element, config) {
			if (element.dataBinding) {
				element.dataBinding.unbind(element);
			}
	
			binding.elements.push(element);
			element.getter 		= (config && typeof config.getter === "function") ? config.getter : binding.getter;
			element.setter 		= (config && typeof config.setter === "function") ? config.setter : binding.setter;
			element.dataBinding 	= binding;
			element.dataBindingPaused = 0;
	
			element.setter(shadowValue);
			var elementValue = element.getter();
			window.setTimeout(function() { // defer adding listeners until the run is done, this is a big performance improvement;
				binding.addListeners(element);
				// find out if our value / element changed since we bound, if so, update;
				if (!isEqual(element.getter(), shadowValue)) {
					changeStack.push(shadowValue);
				}
				if (!isEqual(element.getter(), elementValue)) {
					changeStack.push(element.getter());
				}
			}, 0);
			if (!binding.resolveTimer) {
				binding.resolveTimer = window.setTimeout(this.resolve, 100);
			}
			binding.cleanupBindings();
		};
	
		this.rebind = function(element, config) {
			// Use this when a DOM node is cloned and the clone needs to be registered with the databinding, without setting its data.
			if (element.dataBinding) {
				element.dataBinding.unbind(element);
			}
			binding.elements.push(element);
			element.getter 		= (config && typeof config.getter === "function") ? config.getter : binding.getter;
			element.setter 		= (config && typeof config.setter === "function") ? config.setter : binding.setter;
			element.dataBinding 	= binding;
			element.dataBindingPaused = 0;
	
			var elementValue = element.getter();
			window.setTimeout(function() { // defer adding listeners until the run is done, this is a big performance improvement;
				binding.addListeners(element);
				// find out if our value / element changed since we bound, if so, update;
				if (!isEqual(element.getter(), shadowValue)) {
					changeStack.push(shadowValue);
				}
				if (!isEqual(element.getter(), elementValue)) {
					changeStack.push(element.getter());
				}
			}, 0);
	
			if (!binding.resolveTimer) {
				binding.resolveTimer = window.setTimeout(this.resolve, 100);
			}
			binding.cleanupBindings();
		};
	
		this.unbind = function(element) {
			if (binding.elements.indexOf(element) > -1) {
				binding.removeListeners(element);
				binding.elements.splice(binding.elements.indexOf(element), 1);
			}
		};
	
		this.cleanupBindings = function() {
			if (binding.elements.length < 2) {
				return;
			}
	
			var inDocument = function(element) {
				if (document.contains && document.contains(element)) {
					return true;
				}
				var parent = element.parentNode;
				while (parent) {
					if (parent === document) {
						return true;
					}
					if (parent.nodeType === document.DOCUMENT_FRAGMENT_NODE) {
						if (parent.host && inDocument(parent.host)) {
							return true;
						}
					}
					parent = parent.parentNode;
				}
				return false;
			};
			binding.elements.forEach(function(element) {
				if (!inDocument(element)) {
					element.markedForRemoval = true;
				} else {
					element.markedForRemoval = false;
				}
			});
	
			if (binding.cleanupTimer) {
				clearTimeout(binding.cleanupTimer);
			}
	
			binding.cleanupTimer = window.setTimeout(function() {
				binding.elements.filter(function(element) {
					if (element.markedForRemoval && !inDocument(element)) {
						element.dataBinding.unbind(element);
						return false;
					}
					element.markedForRemoval = false;
					return true;
				});
			}, 1000); // If after 1 second the element is still not in the dom, remove the binding;
		};
	
		initBindings(data, key);
		// Call the custom init function, if it is there;
		if (typeof binding.config.init === "function") {
			binding.config.init.call(binding);
		}
	
		if (binding.mode == "list") {
			document.addEventListener("databind:resolved", function() {
				if (!binding.skipOldValueUpdate) {
					oldValue = dereference(binding.get());
				}
			});
		}
	};
	
	var fieldNodeRemovedHandler = function(evt) {
		if (!this.parentNode && this.dataBinding) {
			this.dataBinding.unbind(this);
		}
	};
	
	dataBinding.prototype.addListeners = function(element) {
		if (element.dataBinding) {
			element.dataBinding.removeListeners(element);
		}
		if (typeof element.mutationObserver === "undefined") {
			if (typeof MutationObserver === "function") {
				element.mutationObserver = new MutationObserver(this.handleMutation);
			}
		}
		if (this.mode == "field") {
			if (element.mutationObserver) {
				element.mutationObserver.observe(element, {attributes: true});
			}
			element.addEventListener("DOMSubtreeModified", this.handleEvent);
			element.addEventListener("DOMNodeRemoved", fieldNodeRemovedHandler);
			element.addEventListener("change", this.handleEvent);
		}
		if (this.mode == "list") {
			if (element.mutationObserver) {
				element.mutationObserver.observe(element, {attributes: true});
			}
			element.addEventListener("DOMNodeRemoved", this.handleEvent);
			element.addEventListener("DOMNodeInserted", this.handleEvent);
		}
		element.addEventListener("databinding:valuechanged", this.handleEvent);
	
		element.addEventListener("databinding:pause", function() {
			this.dataBinding.pauseListeners(this);
		});
		element.addEventListener("databinding:resume", function() {
			this.dataBinding.resumeListeners(this);
		});
	};
	dataBinding.prototype.resumeListeners = function(element) {
		element.dataBindingPaused--;
		if (element.dataBindingPaused < 0) {
			console.log("Warning: resume called of non-paused databinding");
			element.dataBindingPaused = 0;
		}
	};
	dataBinding.prototype.pauseListeners = function(element) {
		element.dataBindingPaused++;
	};
	dataBinding.prototype.removeListeners = function(element) {
		if (this.mode == "field") {
			if (element.mutationObserver) {
				element.mutationObserver.disconnect();
			}
			element.removeEventListener("DOMSubtreeModified", this.handleEvent);
			element.removeEventListener("DOMNodeRemoved", fieldNodeRemovedHandler);
			element.removeEventListener("change", this.handleEvent);
		}
		if (this.mode == "list") {
			if (element.mutationObserver) {
				element.mutationObserver.disconnect();
			}
			element.removeEventListener("DOMNodeRemoved", this.handleEvent);
			element.removeEventListener("DOMNodeInserted", this.handleEvent);
		}
		element.removeEventListener("databinding:valuechanged", this.handleEvent);
	};
	
	dataBinding.prototype.handleMutation = function(event) {
		// FIXME: assuming that one set of mutation events always have the same target; this might not be the case;
		var target = event[0].target;
		if (!target.dataBinding) {
			return;
		}
		if (target.dataBindingPaused) {
			return;
		}
	
		if (target.dataBinding.paused) {
			return;
		}
		var handleMe = false;
		for (var i=0; i<event.length; i++) {
			if (target.dataBinding.attributeFilter.indexOf(event[i].attributeName) == -1) {
				handleMe = true; // only handle the event 
			}
		}
	
		if (handleMe) {
			var self = target.dataBinding;
			window.setTimeout(function() {
				self.pauseListeners(target);	// prevent possible looping, getter sometimes also triggers an attribute change;
				self.set(target.getter());
				self.resumeListeners(target);
			}, 0); // allow the rest of the mutation event to occur;
		}
	};
	
	dataBinding.prototype.handleEvent = function (event) {
		var target = event.currentTarget;
		var self = target.dataBinding;
	
		if (typeof self === 'undefined') {
			return;
		}
		if (self.paused) {
			return;
		}
		if (target.dataBindingPaused) {
			event.stopPropagation();
			return;
		}
		if (self.mode === "list") {
			if (event.relatedNode && (target != event.relatedNode)) {
				return;
			}
		}
	
		var i, data, items;
		if (self.mode === "list" && event.type == "DOMNodeRemoved") {
			if (event.target.nodeType != document.ELEMENT_NODE) {
				return;
			}
			console.time("removing node");
			// find the index of the removed target node;
			items = this.querySelectorAll(":scope > [data-simply-list-item]");
			for (i=0; i<items.length; i++) {
				if (items[i] == event.target) {
					data = target.dataBinding.get();
					items[i].simplyData = data.splice(i, 1)[0];
					return;
				}
			}
			console.timeEnd("removing node");
		}
	
		if (self.mode === "list" && event.type == "DOMNodeInserted") {
			// find the index of the inserted target node;
			items = this.querySelectorAll(":scope > [data-simply-list-item]");
			for (i=0; i<items.length; i++) {
				if (items[i] == event.target) {
					if (items[i].simplyData) {
						data = target.dataBinding.get();
						data.splice(i, 0, items[i].simplyData);
						return;
					}
				}
			}
		}
	
		switch (event.type) {
			case "DOMCharacterDataModified":
			case "databinding:valuechanged":
			case "change":
			case "DOMAttrModified":
			case "DOMNodeInserted":
			case "DOMSubtreeModified":
			case "DOMNodeRemoved":
				// Allow the browser to fix what it thinks needs to be fixed (node to be removed, cleaned etc) before setting the new data;
	
				// there are needed to keep the focus in an element while typing;
				self.pauseListeners(target);
				self.set(target.getter());
				self.resumeListeners(target);
	
				// these are needed to update after the browser is done doing its thing;
				window.setTimeout(function() {
					self.pauseListeners(target);
					self.set(target.getter());
					self.resumeListeners(target);
				}, 1); // allow the rest of the mutation event to occur;
			break;
		}
		self.fireEvent(target, "domchanged");
	};
	
	// Housekeeping, remove references to deleted nodes
	document.addEventListener("DOMNodeRemoved", function(evt) {
		var target = evt.target;
		if (target.nodeType != document.ELEMENT_NODE) { // We don't care about removed text nodes;
			return;
		}
		if (!target.dataBinding) { // nor any element that doesn't have a databinding;
			return;
		}
		window.setTimeout(function() { // chrome sometimes 'helpfully' removes the element and then inserts it back, probably as a rendering optimalization. We're fine cleaning up in a bit, if still needed.
			if (!target.parentNode && target.dataBinding) {
				target.dataBinding.unbind(target);
				delete target.dataBinding;
			}
		}, 1000);
	});
	
	// polyfill to add :scope selector for IE
	(function() {
	  if (!HTMLElement.prototype.querySelectorAll) {
	    throw new Error('rootedQuerySelectorAll: This polyfill can only be used with browsers that support querySelectorAll');
	  }
	
	  // A temporary element to query against for elements not currently in the DOM
	  // We'll also use this element to test for :scope support
	  var container = document.createElement('div');
	
	  // Check if the browser supports :scope
	  try {
	    // Browser supports :scope, do nothing
	    container.querySelectorAll(':scope *');
	  }
	  catch (e) {
	    // Match usage of scope
	    var scopeRE = /\s*:scope/gi;
	
	    // Overrides
	    function overrideNodeMethod(prototype, methodName) {
	      // Store the old method for use later
	      var oldMethod = prototype[methodName];
	
	      // Override the method
	      prototype[methodName] = function(query) {
	        var nodeList,
	            gaveId = false,
	            gaveContainer = false;
	
	        if (query.match(scopeRE)) {
	          if (!this.parentNode) {
	            // Add to temporary container
	            container.appendChild(this);
	            gaveContainer = true;
	          }
	
	          parentNode = this.parentNode;
	
	          if (!this.id) {
	            // Give temporary ID
	            this.id = 'rootedQuerySelector_id_'+(new Date()).getTime();
	            gaveId = true;
	          }
	
	          // Remove :scope
	          query = query.replace(scopeRE, '#' + this.id + " ");
	
	          // Find elements against parent node
	          // nodeList = oldMethod.call(parentNode, '#'+this.id+' '+query);
	          nodeList = parentNode[methodName](query);
	          // Reset the ID
	          if (gaveId) {
	            this.id = '';
	          }
	
	          // Remove from temporary container
	          if (gaveContainer) {
	            container.removeChild(this);
	          }
	
	          return nodeList;
	        }
	        else {
	          // No immediate child selector used
	          return oldMethod.call(this, query);
	        }
	      };
	    }
	
	    // Browser doesn't support :scope, add polyfill
	    overrideNodeMethod(HTMLElement.prototype, 'querySelector');
	    overrideNodeMethod(HTMLElement.prototype, 'querySelectorAll');
	  }
	}());

	editor.init({
		endpoint : document.querySelector("[data-simply-endpoint]") ? document.querySelector("[data-simply-endpoint]").getAttribute("data-simply-endpoint") : null,
		toolbars : defaultToolbars,
		profile : 'live'
	});
}());
