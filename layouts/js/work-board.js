(function () {
    var board = document.querySelector(".work-board");
    if (!board) return;
    var url = board.getAttribute("data-move") || "";
    var token = board.getAttribute("data-csrf") || "";
    var busy = false;

    function cssAttr(value) {
        return String(value).replace(/\\/g, "\\\\").replace(/"/g, '\\"');
    }

    function columnCount(column) {
        var count = column.querySelector(".work-board__count");
        var cards = column.querySelector(".work-board__cards");
        if (count && cards) count.textContent = String(cards.children.length);
    }

    function apply(card, data) {
        if (!data || !data.ok) return;
        var column = board.querySelector('.work-board__col[data-status="' + cssAttr(data.status) + '"]');
        var cards = column ? column.querySelector(".work-board__cards") : null;
        if (!cards) return;
        var previous = card.closest(".work-board__col");
        cards.appendChild(card);
        card.setAttribute("data-status", data.status);
        card.classList.remove("is-settled");
        void card.offsetWidth;
        card.classList.add("is-settled");
        if (previous) columnCount(previous);
        columnCount(column);
        var log = card.querySelector(".work-card__log");
        if (log) {
            log.textContent = data.log || "";
            log.hidden = !data.log;
        }
        var owner = card.querySelector(".work-card__owner");
        var ownerName = card.querySelector(".work-card__owner-name");
        var mark = owner ? owner.querySelector(".mark") : null;
        if (ownerName) {
            ownerName.textContent = data.owner || "";
        } else if (owner) {
            owner.textContent = data.owner || "";
        }
        if (mark) {
            var parts = String(data.owner || "").trim().split(/\s+/).filter(Boolean);
            mark.textContent = parts.slice(0, 2).map(function (part) {
                return part.charAt(0).toUpperCase();
            }).join("");
        }
        if (owner) owner.hidden = !data.owner;
        var select = card.querySelector("select");
        if (select) {
            var current = data.status;
            select.value = "";
            Array.prototype.forEach.call(select.options, function (option) {
                option.hidden = option.value !== "" && option.value === current;
            });
        }
    }

    function move(card, status) {
        if (busy || !card || !status || card.getAttribute("data-status") === status) return;
        busy = true;
        var body = new URLSearchParams();
        body.set("item_id", card.getAttribute("data-item") || "");
        body.set("status", status);
        body.set("_csrf", token);
        fetch(url, {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded", "Accept": "application/json"},
            body: body.toString()
        }).then(function (response) { return response.json(); }).then(function (data) {
            apply(card, data);
        }).catch(function () {}).finally(function () {
            busy = false;
        });
    }

    board.addEventListener("dragstart", function (event) {
        var card = event.target.closest(".work-card");
        if (!card || event.target.closest("select")) {
            event.preventDefault();
            return;
        }
        event.dataTransfer.setData("text/plain", card.getAttribute("data-item") || "");
        event.dataTransfer.effectAllowed = "move";
        card.classList.add("is-dragging");
    });
    board.addEventListener("dragend", function (event) {
        var card = event.target.closest(".work-card");
        if (card) card.classList.remove("is-dragging");
    });
    board.addEventListener("change", function (event) {
        var select = event.target.closest("select");
        if (!select || !select.value) return;
        move(select.closest(".work-card"), select.value);
    });
    board.querySelectorAll(".work-board__col[data-status]").forEach(function (column) {
        column.addEventListener("dragover", function (event) {
            event.preventDefault();
            column.classList.add("is-over");
        });
        column.addEventListener("dragleave", function () {
            column.classList.remove("is-over");
        });
        column.addEventListener("drop", function (event) {
            event.preventDefault();
            column.classList.remove("is-over");
            var id = event.dataTransfer.getData("text/plain");
            var card = board.querySelector('.work-card[data-item="' + cssAttr(id) + '"]');
            move(card, column.getAttribute("data-status") || "");
        });
    });
})();
