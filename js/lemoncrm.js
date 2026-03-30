/* LemonCRM - Global quick-log button + popup window */

$(function() {
	// Don't run inside popup or on login page
	if (window.name === "lcrm_popup") return;
	if (document.body.classList.contains("bodylogin")) return;
	if (typeof lcrm_base === "undefined") return;

	// Build quicklog button if not present
	if (!document.getElementById("lcrm-quicklog")) {
		var types = (typeof lcrm_types !== "undefined" && lcrm_types.length) ? lcrm_types : [
			{code: "LCRM_TEL", icon: "fas fa-phone-alt", label: "Appel"},
			{code: "LCRM_EMAIL", icon: "fas fa-envelope", label: "Email"},
			{code: "LCRM_LINKEDIN", icon: "fab fa-linkedin", label: "LinkedIn"},
			{code: "LCRM_TEAMS", icon: "fas fa-video", label: "Teams"},
			{code: "LCRM_RDV", icon: "far fa-calendar-check", label: "RDV"},
			{code: "LCRM_NOTE", icon: "far fa-comment", label: "Note"}
		];
		var h = '<div class="lcrm-quicklog" id="lcrm-quicklog">';
		h += '<button type="button" class="lcrm-quicklog-btn" id="lcrm-quicklog-toggle" title="Nouvelle interaction">';
		h += '<span class="fa fa-comments"></span></button>';
		h += '<div class="lcrm-quicklog-panel" id="lcrm-quicklog-panel">';
		h += '<div class="lcrm-quicklog-context" id="lcrm-quicklog-ctx" style="display:none">';
		h += '<span class="lcrm-ctx-building fa fa-building-o" aria-hidden="true"></span>';
		h += '<span class="lcrm-ctx-inline"><span id="lcrm-ctx-name" class="lcrm-ctx-name"></span>';
		h += '<a href="#" id="lcrm-ctx-link" class="lcrm-ctx-link" title="Ouvrir la fiche du tiers" target="_blank" rel="noopener noreferrer"><span class="fa fa-link"></span></a>';
		h += '</span></div>';
		h += '<div class="lcrm-quicklog-search">';
		h += '<input type="text" id="lcrm-search-soc" placeholder="Changer de tiers" autocomplete="off" />';
		h += '</div>';
		h += '<div class="lcrm-quicklog-title">NOUVELLE INTERACTION</div>';
		for (var i = 0; i < types.length; i++) {
			h += '<a href="#" class="lcrm-quicklog-item" data-type="' + types[i].code + '">';
			h += '<span class="' + types[i].icon + '"></span><span>' + types[i].label + '</span></a>';
		}
		h += '</div></div>';
		$("body").append(h);
	}

	var baseUrl = (typeof lcrm_base !== "undefined") ? lcrm_base : "/custom/lemoncrm/interaction_card.php";
	var popupRef = null;

	var persistMode = (typeof lcrm_persist !== "undefined") ? lcrm_persist : 0;

	// Detect socid from current page
	function lcrm_detect_socid() {
		if (persistMode == 1) {
			// Persistent mode: manual selection wins over page context
			try {
				var stored = parseInt(sessionStorage.getItem("lcrm_socid") || "0", 10);
				if (stored > 0) return stored;
			} catch (e) {}
		}

		// From PHP hook (authoritative: if defined and > 0, it wins)
		if (typeof lcrm_page_socid !== "undefined" && lcrm_page_socid > 0) return lcrm_page_socid;

		// From URL params (socid, fk_soc)
		var params = new URLSearchParams(window.location.search);
		var directId = params.get("socid") || params.get("fk_soc");
		if (directId) return parseInt(directId);

		if (persistMode == 0) {
			// Default mode: sessionStorage as fallback only
			try {
				var stored = parseInt(sessionStorage.getItem("lcrm_socid") || "0", 10);
				if (stored > 0) return stored;
			} catch (e) {}
		}

		return 0;
	}

	// Apply context display (name + link)
	function lcrm_apply_context(socid, socname) {
		if (!socid || socid <= 0) return;
		window.lcrm_page_socid = socid;
		window.lcrm_page_socname = socname || "";
		$("#lcrm-ctx-name").text(window.lcrm_page_socname);
		$("#lcrm-ctx-link").attr("href", lcrm_dol_root + "/societe/card.php?socid=" + socid);
		$("#lcrm-quicklog-ctx").show();
	}

	// Persist selected tiers in sessionStorage
	function lcrm_set_session_thirdparty(socid, socname) {
		try {
			sessionStorage.setItem("lcrm_socid", String(socid || 0));
			sessionStorage.setItem("lcrm_socname", String(socname || ""));
		} catch (e) {}
	}

	window.lcrm_open_drawer = function(type, socid, contactid, fk_parent) {
		var s = socid || lcrm_detect_socid();
		var c = contactid || 0;
		var url = baseUrl + "?action=create&popup=1";
		if (type) url += "&interaction_type=" + type;
		if (s) url += "&socid=" + s;
		if (c) url += "&contactid=" + c;
		if (fk_parent) url += "&fk_parent=" + fk_parent;

		// Position: right side of screen
		var w = 560;
		var h = Math.min(750, screen.availHeight - 50);
		var left = screen.availWidth - w - 10;
		var top = 30;

		// If popup already open, focus it. If different URL, navigate it.
		if (popupRef && !popupRef.closed) {
			popupRef.location.href = url;
			popupRef.focus();
		} else {
			popupRef = window.open(url, "lcrm_popup",
				"width=" + w + ",height=" + h + ",left=" + left + ",top=" + top +
				",resizable=yes,scrollbars=yes,status=no,menubar=no,toolbar=no,location=no"
			);
		}

		$("#lcrm-quicklog-panel").removeClass("open");
	};

	// Show context (thirdparty name) in quicklog panel
	var detectedSoc = lcrm_detect_socid();
	if (detectedSoc > 0) {
		var socName = (typeof lcrm_page_socname !== "undefined" && lcrm_page_socname) ? lcrm_page_socname : "";
		// Try sessionStorage name if page didn't provide one
		if (!socName) {
			try { socName = sessionStorage.getItem("lcrm_socname") || ""; } catch (e) {}
		}
		if (!socName) {
			// Try to find the name from the societe link text
			$("a[href]").each(function() {
				var href = $(this).attr("href") || "";
				if (href.match(/societe\/card\.php/) && $(this).text().trim().length > 1) {
					var txt = $(this).text().trim();
					// Skip generic tab labels like "Tiers", "Client", etc.
					if (!txt.match(/^(Tiers|Client|Fournisseur|Prospect)$/i)) {
						socName = txt;
						return false;
					}
				}
			});
		}
		// Fallback: read the banner title (first big company name on the page)
		if (!socName) {
			var banner = $(".refidno a[href*='societe'], .fichecenter .refidno a").first();
			if (banner.length && banner.text().trim()) {
				socName = banner.text().trim();
			}
		}
		if (!socName) {
			// Last resort: the big title link at top of thirdparty-related pages
			var titleLink = $("div.fiche a.classfortooltip[href*='societe']").first();
			if (titleLink.length) socName = titleLink.text().trim();
		}
		if (socName) {
			lcrm_apply_context(detectedSoc, socName);
		}
	}

	// Event handlers
	$(document).on("click", "#lcrm-quicklog-toggle", function(e) {
		e.stopPropagation();
		$("#lcrm-quicklog-panel").toggleClass("open");
	});

	$(document).on("click", ".lcrm-quicklog-item", function(e) {
		e.preventDefault();
		lcrm_open_drawer($(this).data("type"));
	});

	$(document).click(function(e) {
		if (!$(e.target).closest("#lcrm-quicklog").length) {
			$("#lcrm-quicklog-panel").removeClass("open");
		}
	});

	// Autocomplete tiers (jQuery UI)
	function lcrm_init_autocomplete() {
		var $input = $("#lcrm-search-soc");
		if (!$input.length) return;

		// If jQuery UI is missing, keep basic behavior
		if (typeof $input.autocomplete !== "function") return;

		$input.autocomplete({
			minLength: 3,
			delay: 200,
			appendTo: "#lcrm-quicklog-panel",
			source: function(request, response) {
				$.get(lcrm_dol_root + "/custom/lemoncrm/ajax/search_company.php", {term: request.term}, function(data) {
					var out = [];
					if (data && data.length) {
						for (var i = 0; i < data.length; i++) {
							out.push({ id: data[i].id, name: data[i].name, label: data[i].name, value: data[i].name });
						}
					}
					response(out);
				}, "json");
			},
			focus: function(event, ui) {
				event.preventDefault();
			},
			select: function(event, ui) {
				event.preventDefault();
				if (ui.item && ui.item.id) {
					lcrm_set_session_thirdparty(ui.item.id, ui.item.name);
					lcrm_apply_context(ui.item.id, ui.item.name);
				}
				$input.val("");
			}
		});

		// Style dropdown
		var $menu = $input.autocomplete("widget");
		$menu.css({ "max-height": "220px", "overflow-y": "auto", "overflow-x": "hidden", "z-index": 100000 });
	}

	lcrm_init_autocomplete();
});
