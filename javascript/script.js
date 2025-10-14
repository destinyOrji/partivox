const loginBtn = document.getElementById("loginBtn");
const popupForm = document.getElementById("popupForm");
const closeBtn = document.getElementById("closeBtn");

loginBtn.onclick = () => {
  popupForm.style.display = "block";
};

closeBtn.onclick = () => {
  popupForm.style.display = "none";
};

// Optional: Close when clicking outside the modal
window.onclick = (e) => {
  if (e.target === popupForm) {
    popupForm.style.display = "none";
  }
};

// Twitter Connect should be a full page navigation, not a fetch
function connectTwitter() {
  window.location.href = '/api/twitter/twitter-auth.php';
}

// Attach to all X wallet and X connect buttons
document.querySelectorAll('.wallet').forEach(wallet => {
  if (wallet.textContent.includes('Twitter') || wallet.textContent.includes('X')) {
    wallet.style.cursor = 'pointer';
    wallet.addEventListener('click', connectTwitter);
  }
});

document.querySelectorAll('#connectXBtn, #connectXBtnLaunch').forEach(btn => {
  btn.addEventListener('click', function(e) {
    e.preventDefault();
    connectTwitter();
  });
});
