(function () {
	'use strict';

	if (typeof window.wpfhAwwData === 'undefined') {
		return;
	}

	var cfg = window.wpfhAwwData;
	var i18n = cfg.i18n || {};

	function showFeedback(el, type, msg) {
		if (!el) {
			return;
		}
		el.className = 'wpfh-aww-feedback is-' + type;
		el.textContent = msg;
		el.hidden = false;
	}

	function postAjax(action, data) {
		var fd = new FormData();
		fd.append('action', action);
		fd.append('nonce', cfg.nonce);
		Object.keys(data || {}).forEach(function (key) {
			var val = data[key];
			if (Array.isArray(val)) {
				val.forEach(function (item) {
					fd.append(key + '[]', item);
				});
			} else if (val !== undefined && val !== null) {
				fd.append(key, String(val));
			}
		});
		return fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: fd
		}).then(function (res) {
			return res.json();
		});
	}

	/* ---------------------------------------------------------------------
	 * 1. Settings Page: Dynamic Provider -> Models & Key Link Switcher
	 * ------------------------------------------------------------------ */
	var providerSelect = document.getElementById('wpfh-aww-provider');
	var modelSelect = document.getElementById('wpfh-aww-model');
	var endpointWrap = document.getElementById('wpfh-aww-endpoint-wrap');
	var keyLinkWrap = document.getElementById('wpfh-aww-key-link-wrap');

	function syncProviderUI() {
		if (!providerSelect || !modelSelect) {
			return;
		}
		var key = providerSelect.value;
		var info = (cfg.providers && cfg.providers[key]) ? cfg.providers[key] : null;
		if (endpointWrap) {
			endpointWrap.hidden = (key !== 'custom');
		}
		if (keyLinkWrap) {
			keyLinkWrap.textContent = '';
			if (info && info.key_url) {
				var a = document.createElement('a');
				a.href = info.key_url;
				a.target = '_blank';
				a.rel = 'noopener noreferrer';
				a.textContent = (i18n.getApiKey || 'Get API Key') + ' \u2192';
				keyLinkWrap.appendChild(a);
			}
		}
		if (info && info.models) {
			var currentVal = modelSelect.value || cfg.savedModel || info.default_model;
			modelSelect.textContent = '';
			var keys = Object.keys(info.models);
			if (keys.length === 0) {
				var optEmpty = document.createElement('option');
				optEmpty.value = info.default_model || '';
				optEmpty.textContent = info.default_model || 'Custom Model';
				modelSelect.appendChild(optEmpty);
			} else {
				keys.forEach(function (mId) {
					var opt = document.createElement('option');
					opt.value = mId;
					opt.textContent = info.models[mId];
					if (mId === currentVal) {
						opt.selected = true;
					}
					modelSelect.appendChild(opt);
				});
			}
		}
	}

	if (providerSelect) {
		providerSelect.addEventListener('change', syncProviderUI);
	}

	/* Test Connection Button */
	var testBtn = document.getElementById('wpfh-aww-test-btn');
	var settingsFeedback = document.getElementById('wpfh-aww-feedback');
	if (testBtn) {
		testBtn.addEventListener('click', function () {
			testBtn.disabled = true;
			showFeedback(settingsFeedback, 'info', i18n.testing || 'Testing...');
			postAjax('wpfh_aww_test_connection', {})
				.then(function (resp) {
					if (resp && resp.success) {
						showFeedback(settingsFeedback, 'success', resp.data.message);
					} else {
						showFeedback(settingsFeedback, 'error', (resp && resp.data && resp.data.message) || i18n.requestFailed);
					}
				})
				.catch(function () {
					showFeedback(settingsFeedback, 'error', i18n.requestFailed);
				})
				.finally(function () {
					testBtn.disabled = false;
				});
		});
	}

	/* ---------------------------------------------------------------------
	 * 2. Product Edit Metabox: Generate Preview & One-Click Apply
	 * ------------------------------------------------------------------ */
	var metabox = document.querySelector('.wpfh-aww-metabox');
	if (metabox) {
		var productId = metabox.getAttribute('data-product-id');
		var metaFeedback = document.getElementById('wpfh-aww-meta-feedback');
		var genBtn = document.getElementById('wpfh-aww-generate-btn');
		var previewBox = document.getElementById('wpfh-aww-preview-box');
		var prevLong = document.getElementById('wpfh-aww-prev-long');
		var prevShort = document.getElementById('wpfh-aww-prev-short');
		var prevBullets = document.getElementById('wpfh-aww-prev-bullets');
		var prevSeo = document.getElementById('wpfh-aww-prev-seo');
		var applyEditorBtn = document.getElementById('wpfh-aww-apply-editor-btn');
		var applySaveBtn = document.getElementById('wpfh-aww-apply-save-btn');
		var appendBulletsCb = document.getElementById('wpfh-aww-append-bullets');

		function getSelectedFields() {
			var list = [];
			metabox.querySelectorAll('.wpfh-aww-field-cb:checked').forEach(function (cb) {
				list.push(cb.value);
			});
			return list;
		}

		function buildFinalLongHtml() {
			var base = prevLong ? prevLong.value.trim() : '';
			var bulletsText = prevBullets ? prevBullets.value.trim() : '';
			if (appendBulletsCb && appendBulletsCb.checked && bulletsText) {
				var lines = bulletsText.split(/\r?\n/).map(function (l) {
					return l.trim();
				}).filter(Boolean);
				if (lines.length > 0) {
					var ul = '<h3>Key Features</h3>\n<ul>\n' + lines.map(function (l) {
						var div = document.createElement('div');
						div.textContent = l;
						return '<li>' + div.innerHTML + '</li>';
					}).join('\n') + '\n</ul>';
					base = base ? (base + '\n\n' + ul) : ul;
				}
			}
			return base;
		}

		if (genBtn) {
			genBtn.addEventListener('click', function () {
				var fields = getSelectedFields();
				if (fields.length === 0) {
					showFeedback(metaFeedback, 'error', i18n.selectField);
					return;
				}

				var titleEl = document.getElementById('title');
				var toneEl = document.getElementById('wpfh-aww-meta-tone');
				var kwEl = document.getElementById('wpfh-aww-meta-keywords');
				var langEl = document.getElementById('wpfh-aww-meta-language');
				var tplEl = document.getElementById('wpfh-aww-meta-template');

				genBtn.disabled = true;
				showFeedback(metaFeedback, 'info', i18n.generating);

				postAjax('wpfh_aww_generate_single', {
					product_id: productId,
					product_title: titleEl ? titleEl.value : '',
					fields: fields,
					tone: toneEl ? toneEl.value : 'persuasive',
					keywords: kwEl ? kwEl.value : '',
					language: langEl ? langEl.value : 'English',
					template_id: tplEl ? tplEl.value : ''
				})
					.then(function (resp) {
						if (resp && resp.success && resp.data) {
							if (prevLong) {
								prevLong.value = resp.data.long_description || '';
							}
							if (prevShort) {
								prevShort.value = resp.data.short_description || '';
							}
							if (prevBullets) {
								prevBullets.value = Array.isArray(resp.data.bullets) ? resp.data.bullets.join('\n') : '';
							}
							if (prevSeo) {
								prevSeo.value = resp.data.seo_meta_description || '';
							}
							if (previewBox) {
								previewBox.hidden = false;
							}
							showFeedback(metaFeedback, 'success', i18n.generated);
						} else {
							showFeedback(metaFeedback, 'error', (resp && resp.data && resp.data.message) || i18n.requestFailed);
						}
					})
					.catch(function () {
						showFeedback(metaFeedback, 'error', i18n.requestFailed);
					})
					.finally(function () {
						genBtn.disabled = false;
					});
			});
		}

		function setEditorValue(editorId, value, overwrite) {
			var textarea = document.getElementById(editorId);
			var tiny = (window.tinymce && window.tinymce.get) ? window.tinymce.get(editorId) : null;
			var current = tiny && !tiny.isHidden() ? tiny.getContent({ format: 'text' }).trim() : (textarea ? textarea.value.trim() : '');
			if (!overwrite && current !== '') {
				return false;
			}
			if (tiny && !tiny.isHidden()) {
				tiny.setContent(value);
				tiny.save();
			}
			if (textarea) {
				textarea.value = value;
				textarea.dispatchEvent(new Event('change', { bubbles: true }));
			}
			return true;
		}

		if (applyEditorBtn) {
			applyEditorBtn.addEventListener('click', function () {
				var fields = getSelectedFields();
				var overwriteEl = document.getElementById('wpfh-aww-meta-overwrite');
				var overwrite = overwriteEl ? overwriteEl.checked : false;

				if (fields.indexOf('long_description') !== -1 || fields.indexOf('bullets') !== -1) {
					setEditorValue('content', buildFinalLongHtml(), overwrite);
				}
				if (fields.indexOf('short_description') !== -1 && prevShort) {
					setEditorValue('excerpt', prevShort.value.trim(), overwrite);
				}
				showFeedback(metaFeedback, 'success', i18n.appliedEditor);
			});
		}

		if (applySaveBtn) {
			applySaveBtn.addEventListener('click', function () {
				var fields = getSelectedFields();
				var overwriteEl = document.getElementById('wpfh-aww-meta-overwrite');
				var syncSeoEl = document.getElementById('wpfh-aww-meta-sync-seo');

				applySaveBtn.disabled = true;
				showFeedback(metaFeedback, 'info', i18n.savingProduct);

				postAjax('wpfh_aww_apply_single', {
					product_id: productId,
					fields: fields,
					long_description: prevLong ? prevLong.value : '',
					short_description: prevShort ? prevShort.value : '',
					bullets: prevBullets ? prevBullets.value : '',
					seo_meta_description: prevSeo ? prevSeo.value : '',
					overwrite: overwriteEl && overwriteEl.checked ? 1 : 0,
					append_bullets: appendBulletsCb && appendBulletsCb.checked ? 1 : 0,
					sync_seo_meta: syncSeoEl && syncSeoEl.checked ? 1 : 0
				})
					.then(function (resp) {
						if (resp && resp.success) {
							showFeedback(metaFeedback, 'success', resp.data.message || i18n.savedProduct);
						} else {
							showFeedback(metaFeedback, 'error', (resp && resp.data && resp.data.message) || i18n.requestFailed);
						}
					})
					.catch(function () {
						showFeedback(metaFeedback, 'error', i18n.requestFailed);
					})
					.finally(function () {
						applySaveBtn.disabled = false;
					});
			});
		}
	}

	/* ---------------------------------------------------------------------
	 * 3. Pro Panels: Bulk AJAX Batch Queue & Brand Voice Templates
	 * ------------------------------------------------------------------ */
	var bulkLoadBtn = document.getElementById('wpfh-aww-bulk-load-btn');
	var bulkStartBtn = document.getElementById('wpfh-aww-bulk-start-btn');
	var bulkStopBtn = document.getElementById('wpfh-aww-bulk-stop-btn');
	var bulkTbody = document.getElementById('wpfh-aww-bulk-tbody');
	var bulkSelectAll = document.getElementById('wpfh-aww-bulk-select-all');
	var bulkProgressWrap = document.getElementById('wpfh-aww-bulk-progress-wrap');
	var bulkProgressFill = document.getElementById('wpfh-aww-bulk-progress-fill');
	var bulkProgressText = document.getElementById('wpfh-aww-bulk-progress-text');
	var stopRequested = false;

	if (bulkSelectAll && bulkTbody) {
		bulkSelectAll.addEventListener('change', function () {
			bulkTbody.querySelectorAll('.wpfh-aww-bulk-cb').forEach(function (cb) {
				cb.checked = bulkSelectAll.checked;
			});
		});
	}

	if (bulkLoadBtn && bulkTbody) {
		bulkLoadBtn.addEventListener('click', function () {
			bulkLoadBtn.disabled = true;
			showFeedback(settingsFeedback, 'info', i18n.loadingProducts);

			postAjax('wpfh_aww_pro_query_products', {
				category: (document.getElementById('wpfh-aww-bulk-cat') || {}).value || '',
				desc_filter: (document.getElementById('wpfh-aww-bulk-desc-filter') || {}).value || 'all',
				search: (document.getElementById('wpfh-aww-bulk-search') || {}).value || ''
			})
				.then(function (resp) {
					bulkTbody.textContent = '';
					if (!resp || !resp.success || !resp.data.products || resp.data.products.length === 0) {
						var emptyTr = document.createElement('tr');
						var emptyTd = document.createElement('td');
						emptyTd.colSpan = 6;
						emptyTd.textContent = i18n.noProducts;
						emptyTr.appendChild(emptyTd);
						bulkTbody.appendChild(emptyTr);
						if (bulkStartBtn) {
							bulkStartBtn.disabled = true;
						}
						showFeedback(settingsFeedback, 'info', i18n.noProducts);
						return;
					}

					resp.data.products.forEach(function (p) {
						var tr = document.createElement('tr');
						tr.setAttribute('data-product-id', String(p.id));

						var tdCb = document.createElement('td');
						var cb = document.createElement('input');
						cb.type = 'checkbox';
						cb.className = 'wpfh-aww-bulk-cb';
						cb.value = String(p.id);
						cb.checked = true;
						tdCb.appendChild(cb);

						var tdId = document.createElement('td');
						tdId.textContent = '#' + p.id;

						var tdName = document.createElement('td');
						tdName.textContent = p.name;

						var tdLong = document.createElement('td');
						tdLong.textContent = p.has_long ? 'Present' : 'Empty';

						var tdShort = document.createElement('td');
						tdShort.textContent = p.has_short ? 'Present' : 'Empty';

						var tdStatus = document.createElement('td');
						tdStatus.className = 'wpfh-aww-row-status';
						tdStatus.textContent = 'Queued';

						tr.appendChild(tdCb);
						tr.appendChild(tdId);
						tr.appendChild(tdName);
						tr.appendChild(tdLong);
						tr.appendChild(tdShort);
						tr.appendChild(tdStatus);
						bulkTbody.appendChild(tr);
					});

					if (bulkStartBtn) {
						bulkStartBtn.disabled = false;
					}
					if (settingsFeedback) {
						settingsFeedback.hidden = true;
					}
				})
				.catch(function () {
					showFeedback(settingsFeedback, 'error', i18n.requestFailed);
				})
				.finally(function () {
					bulkLoadBtn.disabled = false;
				});
		});
	}

	if (bulkStartBtn && bulkTbody) {
		bulkStartBtn.addEventListener('click', function () {
			var selected = [];
			bulkTbody.querySelectorAll('.wpfh-aww-bulk-cb:checked').forEach(function (cb) {
				selected.push(cb.value);
			});
			if (selected.length === 0) {
				showFeedback(settingsFeedback, 'error', i18n.selectProduct);
				return;
			}

			stopRequested = false;
			bulkStartBtn.disabled = true;
			if (bulkLoadBtn) {
				bulkLoadBtn.disabled = true;
			}
			if (bulkStopBtn) {
				bulkStopBtn.hidden = false;
			}
			if (bulkProgressWrap) {
				bulkProgressWrap.hidden = false;
			}

			var total = selected.length;
			var doneCount = 0;

			function processNext(idx) {
				if (stopRequested || idx >= total) {
					bulkStartBtn.disabled = false;
					if (bulkLoadBtn) {
						bulkLoadBtn.disabled = false;
					}
					if (bulkStopBtn) {
						bulkStopBtn.hidden = true;
					}
					showFeedback(settingsFeedback, stopRequested ? 'info' : 'success', stopRequested ? i18n.queueStopped : i18n.queueComplete);
					return;
				}

				var pid = selected[idx];
				var row = bulkTbody.querySelector('tr[data-product-id="' + pid + '"]');
				var statusCell = row ? row.querySelector('.wpfh-aww-row-status') : null;
				if (statusCell) {
					statusCell.textContent = 'Generating...';
				}

				postAjax('wpfh_aww_pro_batch_item', {
					product_id: pid,
					tone: (document.getElementById('wpfh-aww-bulk-tone') || {}).value || 'persuasive',
					language: (document.getElementById('wpfh-aww-bulk-language') || {}).value || 'English',
					template_id: (document.getElementById('wpfh-aww-bulk-template') || {}).value || '',
					overwrite: (document.getElementById('wpfh-aww-bulk-overwrite') || {}).checked ? 1 : 0,
					sync_seo_meta: (document.getElementById('wpfh-aww-bulk-sync-seo') || {}).checked ? 1 : 0
				})
					.then(function (resp) {
						if (statusCell) {
							statusCell.textContent = (resp && resp.success) ? (resp.data.message || 'Done') : ('Error: ' + ((resp && resp.data && resp.data.message) || 'Failed'));
						}
					})
					.catch(function () {
						if (statusCell) {
							statusCell.textContent = 'Error';
						}
					})
					.finally(function () {
						doneCount += 1;
						var pct = Math.round((doneCount / total) * 100);
						if (bulkProgressFill) {
							bulkProgressFill.style.width = pct + '%';
						}
						if (bulkProgressText) {
							bulkProgressText.textContent = doneCount + ' / ' + total + ' (' + pct + '%)';
						}
						processNext(idx + 1);
					});
			}

			showFeedback(settingsFeedback, 'info', i18n.queueRunning);
			processNext(0);
		});
	}

	if (bulkStopBtn) {
		bulkStopBtn.addEventListener('click', function () {
			stopRequested = true;
		});
	}

	/* Brand Voice Template CRUD */
	var tplSaveBtn = document.getElementById('wpfh-aww-tpl-save-btn');
	var tplTbody = document.getElementById('wpfh-aww-tpl-tbody');
	if (tplSaveBtn) {
		tplSaveBtn.addEventListener('click', function () {
			var nameEl = document.getElementById('wpfh-aww-tpl-name');
			var promptEl = document.getElementById('wpfh-aww-tpl-prompt');
			var name = nameEl ? nameEl.value.trim() : '';
			var prompt = promptEl ? promptEl.value.trim() : '';
			if (!name || !prompt) {
				showFeedback(settingsFeedback, 'error', i18n.templateEmpty);
				return;
			}
			tplSaveBtn.disabled = true;
			postAjax('wpfh_aww_pro_save_template', { name: name, prompt: prompt })
				.then(function (resp) {
					if (resp && resp.success) {
						showFeedback(settingsFeedback, 'success', i18n.templateSaved);
						window.location.reload();
					} else {
						showFeedback(settingsFeedback, 'error', (resp && resp.data && resp.data.message) || i18n.requestFailed);
					}
				})
				.catch(function () {
					showFeedback(settingsFeedback, 'error', i18n.requestFailed);
				})
				.finally(function () {
					tplSaveBtn.disabled = false;
				});
		});
	}

	if (tplTbody) {
		tplTbody.addEventListener('click', function (e) {
			var btn = e.target.closest('.wpfh-aww-tpl-delete-btn');
			if (!btn) {
				return;
			}
			var tplId = btn.getAttribute('data-tpl-id');
			btn.disabled = true;
			postAjax('wpfh_aww_pro_delete_template', { template_id: tplId })
				.then(function (resp) {
					if (resp && resp.success) {
						var tr = btn.closest('tr');
						if (tr) {
							tr.remove();
						}
						showFeedback(settingsFeedback, 'success', i18n.templateDeleted);
					}
				})
				.catch(function () {
					showFeedback(settingsFeedback, 'error', i18n.requestFailed);
					btn.disabled = false;
				});
		});
	}
})();
