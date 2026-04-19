// animation cards
document.querySelectorAll(".card-hover").forEach((c, i) => {
  c.style.opacity = 0;
  c.style.transform = "translateY(10px)";
  setTimeout(() => {
    c.style.transition = "all .25s ease";
    c.style.opacity = 1;
    c.style.transform = "translateY(0)";
  }, 60 * i);
});

// toast global
function showToast(message, type = "dark") {
  const toastEl = document.getElementById("appToast");
  const toastBody = document.getElementById("appToastBody");

  if (!toastEl || !toastBody) return;

  toastEl.className = "toast align-items-center border-0 text-bg-" + type;
  toastBody.textContent = message;

  const toast = bootstrap.Toast.getOrCreateInstance(toastEl, {
    delay: 2500
  });

  toast.show();
}