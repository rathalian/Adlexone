(function () {
    var catalogNode = document.querySelector("[data-rule-catalog]");
    var catalog = JSON.parse(catalogNode ? catalogNode.getAttribute("data-rule-catalog") || "{}" : "{}");
    var form = document.querySelector("form");
    if (!form) return;
    var type = form.querySelector(".rule-type");
    var operator = form.querySelector(".rule-operator");
    var eventSel = form.querySelector(".rule-event");
    var fieldWrap = form.querySelector(".rule-field");
    var fromWrap = form.querySelector(".rule-from");
    var valueWrap = form.querySelector(".rule-value");
    var logRole = form.querySelector(".rule-log-role");
    function optionsFor(key) {
        var fields = catalog[type ? type.value : ""] || [];
        for (var i = 0; i < fields.length; i++) {
            if (fields[i].key === key) return fields[i].options || [];
        }
        return [];
    }
    function fillSelect(select, rows, blank) {
        if (!select) return;
        var current = select.value;
        select.innerHTML = "";
        if (blank) {
            var any = document.createElement("option");
            any.value = "";
            any.textContent = "Any record";
            select.appendChild(any);
        }
        rows.forEach(function (row) {
            var opt = document.createElement("option");
            opt.value = row.key || row.value;
            opt.textContent = row.label;
            select.appendChild(opt);
        });
        if (current) select.value = current;
    }
    function syncMatch() {
        var op = operator ? operator.value : "always";
        var hideValue = op === "always" || op === "empty" || op === "not_empty" || op === "changed";
        if (fieldWrap) fieldWrap.hidden = op === "always";
        if (fromWrap) fromWrap.hidden = op !== "from_to";
        if (valueWrap) valueWrap.hidden = hideValue;
        if (logRole) logRole.hidden = !eventSel || eventSel.value !== "log";
        ["match_from", "match_value"].forEach(function (name) {
            var wrap = name === "match_from" ? fromWrap : valueWrap;
            if (!wrap) return;
            var select = wrap.querySelector("select");
            var text = wrap.querySelector("input");
            var choice = wrap.querySelector(".rule-value-choice");
            var textWrap = wrap.querySelector(".rule-value-text");
            var key = form.querySelector("[name=field_key]");
            var opts = optionsFor(key ? key.value : "");
            if (select && opts.length) {
                fillSelect(select, opts, false);
                if (choice) choice.hidden = false;
                if (textWrap) textWrap.hidden = true;
            } else {
                if (choice) choice.hidden = true;
                if (textWrap) textWrap.hidden = false;
            }
            if (select && text && choice && !choice.hidden) text.value = select.value;
        });
    }
    function syncSteps() {
        form.querySelectorAll(".rule-step").forEach(function (step) {
            var outcome = step.querySelector(".rule-outcome");
            var value = outcome ? outcome.value : "";
            step.querySelectorAll(".rule-step-panel").forEach(function (panel) {
                var hide = panel.getAttribute("data-for") !== value;
                panel.hidden = hide;
                panel.querySelectorAll("input, textarea, select").forEach(function (el) {
                    el.disabled = hide;
                });
            });
            var recipient = step.querySelector(".rule-recipient");
            var kind = recipient ? recipient.value : "";
            var user = step.querySelector(".rule-recipient-user");
            var group = step.querySelector(".rule-recipient-group");
            var address = step.querySelector(".rule-recipient-address");
            if (user) user.hidden = kind !== "user";
            if (group) group.hidden = kind !== "group";
            if (address) address.hidden = kind !== "address";
        });
    }
    function syncTokens() {
        var host = form.querySelector(".rule-token-list");
        if (!host) return;
        host.innerHTML = "";
        var tokens = [
            ["ITEM_ID", "Record id"],
            ["ITEM_TITLE", "Title"],
            ["ITEM_CREATOR", "Creator"],
            ["ITEM_OWNER", "Owner"]
        ];
        (catalog[type ? type.value : ""] || []).forEach(function (field) {
            var match = /^custom_field_(\d+)$/.exec(field.key || "");
            if (match) tokens.push(["CUSTOM_FIELD_" + match[1], field.label]);
        });
        tokens.forEach(function (token) {
            var button = document.createElement("button");
            button.type = "button";
            button.className = "btn btn--sm";
            button.textContent = token[1];
            button.addEventListener("click", function () {
                var target = form.querySelector(".rule-token-target:focus") || form.querySelector("[name='steps[0][body]']");
                if (!target) return;
                var start = target.selectionStart || target.value.length;
                var end = target.selectionEnd || start;
                target.value = target.value.slice(0, start) + token[0] + target.value.slice(end);
                target.focus();
            });
            host.appendChild(button);
            host.appendChild(document.createTextNode(" "));
        });
    }
    function syncFields() {
        var rows = catalog[type ? type.value : ""] || [];
        fillSelect(form.querySelector("[name=field_key]"), rows, true);
        form.querySelectorAll("[name$='[action_field]']").forEach(function (select) {
            var keep = [];
            rows.forEach(function (row) {
                if (row.key === "owner" || row.key === "creator" || row.key === "groups") return;
                keep.push(row);
            });
            fillSelect(select, keep, false);
        });
        syncMatch();
        syncTokens();
    }
    form.addEventListener("change", function (event) {
        var name = event.target && event.target.name;
        if (name === "item_type_id") syncFields();
        if (name === "operator" || name === "field_key" || name === "event" || name === "match_value_choice" || name === "match_from_choice") syncMatch();
        if (event.target && (event.target.classList.contains("rule-outcome") || event.target.classList.contains("rule-recipient"))) syncSteps();
    });
    form.addEventListener("submit", function () {
        ["match_from", "match_value"].forEach(function (name) {
            var choice = form.querySelector("[name='" + name + "_choice']");
            var text = form.querySelector("[name='" + name + "']");
            var wrap = name === "match_from" ? fromWrap : valueWrap;
            var choiceWrap = wrap ? wrap.querySelector(".rule-value-choice") : null;
            if (choice && text && choiceWrap && !choiceWrap.hidden) text.value = choice.value;
        });
    });
    syncFields();
    syncSteps();
})();
