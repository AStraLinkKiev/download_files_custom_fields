(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		document.querySelectorAll('.com-prodfiles').forEach(function (root) {
			initSearch(root);
			initSelectAll(root);
		});
	});

	function initSearch(root) {
		var input = root.querySelector('#prodfiles-product-search');
		var results = root.querySelector('[data-prodfiles-results]');
		var searchUrl = root.getAttribute('data-search-url');
		var controller = null;
		var timer = null;

		if (!input || !results || !searchUrl) {
			return;
		}

		input.addEventListener('input', function () {
			clearTimeout(timer);
			timer = setTimeout(function () {
				runSearch();
			}, 220);
		});

		input.addEventListener('focus', function () {
			if (input.value.trim().length >= 2) {
				runSearch();
			}
		});

		document.addEventListener('click', function (event) {
			if (!root.contains(event.target)) {
				results.hidden = true;
			}
		});

		function runSearch() {
			var query = input.value.trim();

			if (query.length < 2) {
				renderResults(results, []);
				return;
			}

			if (controller) {
				controller.abort();
			}

			controller = new AbortController();

			fetch(searchUrl + '&q=' + encodeURIComponent(query), {
				credentials: 'same-origin',
				signal: controller.signal
			})
				.then(function (response) {
					if (!response.ok) {
						throw new Error('Search request failed');
					}

					return response.json();
				})
				.then(function (payload) {
					renderResults(results, payload && payload.data ? payload.data : []);
				})
				.catch(function (error) {
					if (error.name !== 'AbortError') {
						renderResults(results, []);
					}
				})
				.finally(function () {
					controller = null;
				});
		}
	}

	function renderResults(container, items) {
		container.innerHTML = '';

		if (!items.length) {
			container.hidden = true;
			return;
		}

		items.forEach(function (item) {
			var link = document.createElement('a');
			var title = document.createElement('span');
			var count = document.createElement('span');

			link.className = 'com-prodfiles__result';
			link.href = item.url;
			title.textContent = item.product_name;
			count.className = 'com-prodfiles__result-count';
			count.textContent = item.files_count;
			link.appendChild(title);
			link.appendChild(count);
			container.appendChild(link);
		});

		container.hidden = false;
	}

	function initSelectAll(root) {
		var toggle = root.querySelector('[data-prodfiles-select-all]');

		if (!toggle) {
			return;
		}

		toggle.addEventListener('change', function () {
			root.querySelectorAll('input[name="files[]"]').forEach(function (input) {
				input.checked = toggle.checked;
			});
		});
	}
}());
