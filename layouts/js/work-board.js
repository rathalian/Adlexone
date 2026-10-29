(function () {
    var board = document.querySelector(".work-board");
    var bulk = document.querySelector(".work-bulk");
    bindBoard(board);
    bindBulk(bulk);
    bindRecord();

    function waitsOf(node) {
        if (!node) return [];
        return String(node.getAttribute("data-waits") || "").split("|").map(function (value) {
            return value.trim();
        }).filter(Boolean);
    }

    function isWait(list, status) {
        return list.indexOf(status) !== -1;
    }

    function bindBoard(board) {
        if (!board) return;
        var url = board.getAttribute("data-move") || "";
        var token = board.getAttribute("data-csrf") || "";
        var waits = waitsOf(board);
        var dialog = board.querySelector("[data-due-ask]");
        var dueInput = dialog ? dialog.querySelector("[data-due-input]") : null;
        var pending = null;
        var busy = false;

        function cssAttr(value) {
            return String(value).replace(/\\/g, "\\\\").replace(/"/g, '\\"');
        }

        function columnCount(column) {
            var count = column.querySelector(".work-board__count");
            var cards = column.querySelector(".work-board__cards");
            if (count && cards) count.textContent = String(cards.children.length);
        }

        function resetSelect(card) {
            var select = card ? card.querySelector("select") : null;
            if (select) select.value = "";
        }

        function apply(card, data) {
            if (!data || !data.ok) {
                resetSelect(card);
                return;
            }
            var column = board.querySelector('.work-board__col[data-status="' + cssAttr(data.status) + '"]');
            var cards = column ? column.querySelector(".work-board__cards") : null;
            if (!cards) return;
            var previous = card.closest(".work-board__col");
            cards.appendChild(card);
            card.setAttribute("data-status", data.status);
            if (dueInput && isWait(waits, data.status)) {
                card.setAttribute("data-due", dueInput.value.trim());
            }
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

        function send(card, status, due) {
            if (busy || !card || !status || card.getAttribute("data-status") === status) return;
            busy = true;
            var body = new URLSearchParams();
            body.set("item_id", card.getAttribute("data-item") || "");
            body.set("status", status);
            if (due) body.set("due", due);
            body.set("_csrf", token);
            fetch(url, {
                method: "POST",
                headers: {"Content-Type": "application/x-www-form-urlencoded", "Accept": "application/json"},
                body: body.toString()
            }).then(function (response) { return response.json(); }).then(function (data) {
                apply(card, data);
            }).catch(function () {
                resetSelect(card);
            }).finally(function () {
                busy = false;
            });
        }

        function ask(card, status) {
            if (!dialog || !dueInput) {
                send(card, status, "");
                return;
            }
            pending = {card: card, status: status};
            dueInput.value = card.getAttribute("data-due") || "";
            if (typeof dialog.showModal === "function") {
                dialog.showModal();
            }
            dueInput.focus();
        }

        function move(card, status) {
            if (busy || !card || !status || card.getAttribute("data-status") === status) return;
            if (isWait(waits, status)) {
                ask(card, status);
                return;
            }
            send(card, status, "");
        }

        if (dialog) {
            dialog.addEventListener("close", function () {
                pending = null;
            });
            var cancel = dialog.querySelector("[data-due-cancel]");
            if (cancel) {
                cancel.addEventListener("click", function () {
                    if (pending) resetSelect(pending.card);
                    pending = null;
                    dialog.close();
                });
            }
            dialog.querySelector("form").addEventListener("submit", function (event) {
                event.preventDefault();
                var value = dueInput ? dueInput.value.trim() : "";
                if (!value || !pending) return;
                var next = pending;
                pending = null;
                dialog.close();
                send(next.card, next.status, value);
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
    }

    function bindBulk(form) {
        if (!form) return;
        var waits = waitsOf(form);
        var status = form.querySelector(".work-bulk__status");
        var due = form.querySelector("[data-due-input]");
        if (!status || !due || waits.length === 0) return;

        function selectedDue() {
            var boxes = form.querySelectorAll("[data-select-row]:checked");
            if (boxes.length !== 1) return "";
            return boxes[0].getAttribute("data-due") || "";
        }

        function sync() {
            var waiting = isWait(waits, status.value);
            form.classList.toggle("is-waiting", waiting);
            due.required = waiting;
            if (waiting && due.value.trim() === "") {
                due.value = selectedDue();
            }
            if (!waiting) {
                due.required = false;
            }
        }

        status.addEventListener("change", sync);
        form.addEventListener("change", function (event) {
            if (event.target && event.target.hasAttribute("data-select-row")) sync();
        });
        sync();
    }

    function bindRecord() {
        var select = document.querySelector("select[data-waits]");
        if (!select) return;
        var waits = waitsOf(select);
        var name = select.getAttribute("data-due-name") || "";
        var due = name ? document.querySelector('[name="' + name.replace(/"/g, "") + '"]') : document.querySelector("[data-due-field]");
        if (!due) return;

        function sync() {
            var waiting = isWait(waits, select.value);
            due.required = waiting;
            var field = due.closest(".field") || due.parentElement;
            if (field) field.classList.toggle("is-waiting", waiting);
        }

        select.addEventListener("change", function () {
            sync();
            if (due.required) due.focus();
        });
        sync();
    }
})();
