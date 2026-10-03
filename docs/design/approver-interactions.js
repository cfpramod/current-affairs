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
    const accepting = button.dataset.outcome === "accept";
    const nextStep = dialog.querySelector("[data-next-step]");
    nextStep.hidden = !accepting;
    form.elements.next_action.required = accepting;
    form.elements.next_action.value = button.dataset.nextAction || "";
    dialog.querySelector("[data-confirmation]").textContent = accepting
      ? "I accept the operator’s request and confirm we will take the next action stated above."
      : "I deny the operator’s request with this reason and will return it to the operator for review.";
    dialog.querySelector("[data-submit-review]").textContent = accepting
      ? "Preview acceptance"
      : "Preview denial";
    dialog.querySelector("[data-error]").textContent = "";
    dialog.showModal();
  });
});
dialog
  .querySelector("[data-cancel]")
  .addEventListener("click", () => dialog.close());
function previewDecision(event) {
  event.preventDefault();
  const reason = form.elements.reason.value.trim();
  const accepting = trigger.dataset.outcome === "accept";
  const nextAction = form.elements.next_action.value.trim();
  if (
    !reason ||
    !form.elements.reviewed.checked ||
    (accepting && !nextAction)
  ) {
    dialog.querySelector("[data-error]").textContent =
      "Add a decision reason, state the next action when accepting, and confirm your decision.";
    return;
  }
  const log = trigger.closest(".card").querySelector("[data-log]");
  log.textContent = `Preview only · ${trigger.dataset.decision} · Approver reason: ${reason} · ${
    accepting
      ? `Next action: ${nextAction} Approver confirms responsibility for this next step.`
      : "Return to the operator with this reason; no substitute action is authorized."
  } No workflow changed.`;
  dialog.close();
  log.focus();
}
dialog.addEventListener("submit", previewDecision);
dialog
  .querySelector("[data-submit-review]")
  .addEventListener("click", previewDecision);
document.documentElement.dataset.reviewReady = "true";
