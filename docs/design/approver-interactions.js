// Wireframe-only interactions; no server requests or approval persistence.
const dialog = document.querySelector("dialog");
const form = dialog.querySelector("form");
let trigger;
document.querySelectorAll("[data-decision]").forEach((button) => {
  button.addEventListener("click", () => {
    trigger = button;
    form.reset();
    dialog.querySelector("h2").textContent = button.dataset.decision;
    dialog.querySelector("[data-context]").textContent = button.dataset.context;
    dialog.querySelector("[data-error]").textContent = "";
    dialog.showModal();
  });
});
dialog
  .querySelector("[data-cancel]")
  .addEventListener("click", () => dialog.close());
form.addEventListener("submit", (event) => {
  event.preventDefault();
  const reason = form.elements.reason.value.trim();
  if (!reason || !form.elements.reviewed.checked) {
    dialog.querySelector("[data-error]").textContent =
      "Add a reason and confirm that you reviewed the evidence.";
    return;
  }
  const log = trigger.closest(".card").querySelector("[data-log]");
  log.textContent = `Preview only · ${trigger.dataset.decision} · Reason: ${reason}. No workflow changed.`;
  dialog.close();
  log.focus();
});
