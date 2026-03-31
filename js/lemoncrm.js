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

/* ========== AI MESSAGE GENERATION ========== */

$(function() {
	// Don't run if AI is not configured
	if (typeof lcrm_ai_url === "undefined") return;

	// Toggle inline AI panel
	$(document).on("click", "#lcrm-ai-toggle", function() {
		$("#lcrm-ai-panel").slideToggle(150);
	});

	// Get current form values for AI context
	function lcrm_ai_get_params(source) {
		var prefix = (source === "drawer") ? "#lcrm-ai-drawer-" : "#lcrm-ai-";
		var socid = $("select[name='fk_soc'], input[name='fk_soc']").val() || $("input[name='socid']").val() || 0;
		// select2 may store value differently
		if (!socid || socid == 0) {
			var s2 = $("#fk_soc");
			if (s2.length) socid = s2.val();
		}
		var contactid = $("select[name='fk_socpeople']").val() || 0;
		var channel = $("input[name='interaction_type']:checked").val() || $("input[name='interaction_type_backup']").val() || "";

		return {
			token: lcrm_ai_token,
			socid: parseInt(socid) || 0,
			contactid: parseInt(contactid) || 0,
			channel: channel,
			objective: $(prefix + "objective").val() || "",
			incoming_message: $(prefix + "incoming").val() || ""
		};
	}

	// Call AI generation endpoint
	function lcrm_ai_generate(source) {
		var params = lcrm_ai_get_params(source);

		if (!params.channel) {
			alert("Selectionnez un type d'interaction d'abord");
			return;
		}

		var prefix = (source === "drawer") ? "#lcrm-ai-drawer-" : "#lcrm-ai-";
		$(prefix + "loading").show();
		$(prefix + "result").hide();
		$(prefix + "generate").prop("disabled", true);

		$.ajax({
			url: lcrm_ai_url,
			type: "POST",
			data: params,
			dataType: "json",
			success: function(data) {
				$(prefix + "loading").hide();
				$(prefix + "generate").prop("disabled", false);

				if (data.success && data.message) {
					$(prefix + "result-content").text(data.message);
					$(prefix + "result").show();
				} else {
					var errorMsg = data.error || "Erreur inconnue";
					$(prefix + "result-content").html('<span style="color:#dc2626">' + $("<span>").text(errorMsg).html() + '</span>');
					$(prefix + "result").show();
				}
			},
			error: function(xhr) {
				$(prefix + "loading").hide();
				$(prefix + "generate").prop("disabled", false);
				$(prefix + "result-content").html('<span style="color:#dc2626">Erreur de connexion au serveur</span>');
				$(prefix + "result").show();
			}
		});
	}

	// Generate buttons (inline + drawer)
	$(document).on("click", "#lcrm-ai-generate, #lcrm-ai-regenerate", function() {
		lcrm_ai_generate("inline");
	});
	$(document).on("click", "#lcrm-ai-drawer-generate, #lcrm-ai-drawer-regenerate", function() {
		lcrm_ai_generate("drawer");
	});

	// Insert into summary WYSIWYG
	function lcrm_ai_insert_to_summary(text) {
		// Try CKEditor first
		if (typeof CKEDITOR !== "undefined" && CKEDITOR.instances && CKEDITOR.instances.summary) {
			CKEDITOR.instances.summary.setData(text.replace(/\n/g, "<br>"));
			return;
		}
		// Fallback: textarea or contenteditable
		var $textarea = $("textarea[name='summary'], #summary");
		if ($textarea.length) {
			$textarea.val(text);
			return;
		}
		var $editor = $(".lcrm-editor[data-name='summary']");
		if ($editor.length) {
			$editor.html(text.replace(/\n/g, "<br>"));
		}
	}

	$(document).on("click", "#lcrm-ai-insert, #lcrm-ai-drawer-insert", function() {
		var text = $(this).closest(".lcrm-ai-result").find(".lcrm-ai-result-content").text();
		lcrm_ai_insert_to_summary(text);
		// Close drawer if open
		$("#lcrm-ai-drawer").hide();
	});

	// Copy to clipboard
	$(document).on("click", "#lcrm-ai-copy, #lcrm-ai-drawer-copy", function() {
		var $btn = $(this);
		var text = $btn.closest(".lcrm-ai-result").find(".lcrm-ai-result-content").text();
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(function() {
				var orig = $btn.html();
				$btn.html('<span class="fas fa-check"></span> Copie !');
				setTimeout(function() { $btn.html(orig); }, 1500);
			});
		} else {
			// Fallback
			var $temp = $("<textarea>");
			$("body").append($temp);
			$temp.val(text).select();
			document.execCommand("copy");
			$temp.remove();
			var orig = $btn.html();
			$btn.html('<span class="fas fa-check"></span> Copie !');
			setTimeout(function() { $btn.html(orig); }, 1500);
		}
	});

	// Drawer toggle
	$(document).on("click", "#lcrm-ai-drawer-toggle", function() {
		var $drawer = $("#lcrm-ai-drawer");
		if ($drawer.is(":visible")) {
			$drawer.hide();
		} else {
			// Sync objective and incoming message from inline panel
			var inlineObj = $("#lcrm-ai-objective").val();
			var inlineMsg = $("#lcrm-ai-incoming").val();
			if (inlineObj) $("#lcrm-ai-drawer-objective").val(inlineObj);
			if (inlineMsg) $("#lcrm-ai-drawer-incoming").val(inlineMsg);
			$drawer.show();
		}
	});

	$(document).on("click", "#lcrm-ai-drawer-close", function() {
		$("#lcrm-ai-drawer").hide();
	});
});
