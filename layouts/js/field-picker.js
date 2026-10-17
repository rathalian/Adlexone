(function () {
  var available = document.getElementById("availableFields");
  var selected = document.getElementById("selectedFields");
  if (!available || !selected) return;
  function renumber() {
    selected.querySelectorAll(".field-picker-item").forEach(function (li, i) {
      var order = li.querySelector("input[type=hidden]");
      if (order) order.value = String(i + 1);
    });
    available.querySelectorAll("input[type=hidden]").forEach(function (order) { order.value = "0"; });
  }
  function place(event) {
    var box = event.target;
    if (!box || box.type !== "checkbox") return;
    var li = box.closest(".field-picker-item");
    (box.checked ? selected : available).appendChild(li);
    renumber();
  }
  available.addEventListener("change", place);
  selected.addEventListener("change", place);
  selected.addEventListener("click", function (event) {
    var button = event.target.closest("[data-move]");
    if (!button) return;
    var li = button.closest(".field-picker-item");
    if (button.getAttribute("data-move") === "up" && li.previousElementSibling) {
      selected.insertBefore(li, li.previousElementSibling);
    } else if (button.getAttribute("data-move") === "down" && li.nextElementSibling) {
      selected.insertBefore(li.nextElementSibling, li);
    }
    renumber();
  });
  renumber();
})();
