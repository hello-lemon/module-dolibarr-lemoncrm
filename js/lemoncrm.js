/* LemonCRM - Global quick-log button + popup window */

$(function() {
	// Don't run inside popup
	if (window.name === "lcrm_popup") return;

	// Build quicklog button if not present
	if (!document.getElementById("lcrm-quicklog")) {
		var types = [
			{code: "AC_TEL", icon: "fas fa-phone-alt", label: "Appel"},
			{code: "AC_EMAIL", icon: "fas fa-envelope", label: "Email"},
			{code: "AC_LINKEDIN", icon: "fas fa-share-alt", label: "LinkedIn"},
			{code: "AC_TEAMS", icon: "fas fa-video", label: "Teams"},
			{code: "AC_RDV", icon: "far fa-calendar-check", label: "RDV"},
			{code: "AC_OTH", icon: "far fa-comment", label: "Note"}
		];
		var h = '<div class="lcrm-quicklog" id="lcrm-quicklog">';
		h += '<button type="button" class="lcrm-quicklog-btn" id="lcrm-quicklog-toggle" title="Nouvelle interaction">';
		h += '<span class="fa fa-comments"></span></button>';
		h += '<div class="lcrm-quicklog-panel" id="lcrm-quicklog-panel">';
		h += '<div class="lcrm-quicklog-context" id="lcrm-quicklog-ctx" style="display:none"><span class="fa fa-building-o"></span> <span id="lcrm-ctx-name"></span></div>';
		h += '<div class="lcrm-quicklog-title">NOUVELLE INTERACTION</div>';
		for (var i = 0; i < types.length; i++) {
			h += '<a href="#" class="lcrm-quicklog-item" data-type="' + types[i].code + '">';
			h += '<span class="fa ' + types[i].icon + '"></span><span>' + types[i].label + '</span></a>';
		}
		h += '</div></div>';
		$("body").append(h);
	}

	var baseUrl = (typeof lcrm_base !== "undefined") ? lcrm_base : "/custom/lemoncrm/interaction_card.php";
	var popupRef = null;

	// Detect socid from current page (JS fallback when PHP hook doesn't run)
	function lcrm_detect_socid() {
		// 1. From PHP hook
		if (typeof lcrm_page_socid !== "undefined" && lcrm_page_socid > 0) return lcrm_page_socid;

		// 2. From URL params (socid, fk_soc)
		var params = new URLSearchParams(window.location.search);
		var directId = params.get("socid") || params.get("fk_soc");
		if (directId) return parseInt(directId);

		// 3. Scan all links on the page for socid
		var found = 0;
		$("a[href]").each(function() {
			if (found) return false;
			var href = $(this).attr("href") || "";
			// societe/card.php?socid=123
			var m = href.match(/societe\/card\.php\?socid=(\d+)/);
			if (m) { found = parseInt(m[1]); return false; }
			// societe/card.php/123
			var m2 = href.match(/societe\/card\.php\/(\d+)/);
			if (m2) { found = parseInt(m2[1]); return false; }
		});
		if (found) return found;

		// 4. Broader: any href containing socid= parameter
		$("a[href*='socid=']").each(function() {
			if (found) return false;
			var m = ($(this).attr("href") || "").match(/socid=(\d+)/);
			if (m) { found = parseInt(m[1]); return false; }
		});
		if (found) return found;

		// 5. Tabs often use relative URLs, check tab links
		$(".tabsElem a[href], .tabs a[href]").each(function() {
			if (found) return false;
			var href = $(this).attr("href") || "";
			var m = href.match(/socid=(\d+)/);
			if (m) { found = parseInt(m[1]); return false; }
		});
		if (found) return found;

		return 0;
	}

	window.lcrm_open_drawer = function(type, socid, contactid) {
		var s = socid || lcrm_detect_socid();
		var c = contactid || 0;
		var url = baseUrl + "?action=create&popup=1";
		if (type) url += "&interaction_type=" + type;
		if (s) url += "&socid=" + s;
		if (c) url += "&contactid=" + c;

		// Position: right side of screen
		var w = 420;
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
			$("#lcrm-ctx-name").text(socName);
			$("#lcrm-quicklog-ctx").show();
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
});
