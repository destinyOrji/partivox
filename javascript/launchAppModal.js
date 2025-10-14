document.addEventListener('DOMContentLoaded', function() {
  const launchAppBtnModal = document.getElementById('launchAppBtnModal');
  const launchAppPopup = document.getElementById('launchAppPopup');
  const closeLaunchBtn = document.getElementById('closeLaunchBtn');

  if (launchAppBtnModal && launchAppPopup && closeLaunchBtn) {
    launchAppBtnModal.onclick = () => {
      launchAppPopup.style.display = 'block';
    };
    closeLaunchBtn.onclick = () => {
      launchAppPopup.style.display = 'none';
    };
    window.onclick = (e) => {
      if (e.target === launchAppPopup) {
        launchAppPopup.style.display = 'none';
      }
    };
  }
});
