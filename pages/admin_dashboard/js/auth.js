// =======================
// API BASE URL
// =======================
const API_BASE_URL = "/";

// =======================
// DOM Elements
// =======================
const signupForm = document.getElementById("signupForm");
const otpForm = document.getElementById("otpForm");
const resendOtpBtn = document.getElementById("resendOtp");
const loginForm = document.getElementById("loginForm");

// Store email for OTP verification
let userEmail = "";

// =======================
// Helper: show error
// =======================
function showError(message) {
  let errorDiv = document.getElementById('errorDisplay');
  if (!errorDiv) {
    errorDiv = document.createElement('div');
    errorDiv.id = 'errorDisplay';
    errorDiv.className = 'alert alert-danger position-fixed top-0 start-50 translate-middle-x mt-3';
    errorDiv.style.zIndex = '9999';
    document.body.appendChild(errorDiv);
  }
  errorDiv.textContent = message;
  errorDiv.style.display = 'block';
  setTimeout(() => { errorDiv.style.display = 'none'; }, 5000);
}

// =======================
// SIGNUP
// =======================
if (signupForm) {
  signupForm.addEventListener("submit", async (e) => {
    e.preventDefault();

    const formData = new FormData(signupForm);
    const data = {
      name: formData.get("name"),
      email: formData.get("email"),
      password: formData.get("password"),
      role: 'admin' // Explicitly set admin role for admin registration
    };
    // Remove this unused line as it's handled below

    console.log("Submitting admin signup form with data:", data);
    console.log("Calling API:", `${API_BASE_URL}api/auth/register`);

    // Show loading overlay
    const loadingOverlay = document.getElementById('loadingOverlay');
    if (loadingOverlay) {
      loadingOverlay.classList.remove('d-none');
    } else {
      console.warn('Loading overlay not found');
    }

    try {
      const response = await fetch(`${API_BASE_URL}api/auth/register`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
      });

      console.log("Response status:", response.status);

      if (!response.ok) {
        const errorText = await response.text();
        console.error("Server responded with error:", errorText);
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const result = await response.json();
      console.log("Signup response:", result);

      if (result.status === "success") {
        userEmail = data.email;
        sessionStorage.setItem("otpEmail", data.email);
        
        // Store OTP in session storage if email failed
        if (result.data && result.data.otp && !result.data.emailSent) {
          sessionStorage.setItem("tempOtp", result.data.otp);
          console.log("Email failed, OTP stored for manual entry:", result.data.otp);
        }
        
        window.location.href = "/pages/admin_dashboard/otp.html";
      } else {
        if (result.message && result.message.toLowerCase().includes("email already registered")) {
          showError("This email is already registered. Please use a different email or login.");
        } else {
          showError(result.message || "Signup failed");
        }
      }
    } catch (error) {
      console.error("Error during admin signup:", error);
      showError("An error occurred during admin registration. Please try again.");
    } finally {
      // Hide loading overlay
      if (loadingOverlay) loadingOverlay.classList.add('d-none');
    }
  });
}

// =======================
// OTP VERIFICATION
// =======================
if (otpForm) {
  // Get email from session storage
  userEmail = sessionStorage.getItem("otpEmail") || "";
  if (userEmail) {
    const emailDisplay = document.getElementById("emailDisplay");
    if (emailDisplay) {
      emailDisplay.textContent = userEmail;
    }
  }

  otpForm.addEventListener("submit", async (e) => {
    e.preventDefault();

    const formData = new FormData(otpForm);
    const data = { email: userEmail, otp: formData.get("otp") };

    console.log("Verifying admin OTP for:", { email: data.email });

    // Show loading overlay
    const loadingOverlay = document.getElementById('loadingOverlay');
    if (loadingOverlay) loadingOverlay.classList.remove('d-none');

    try {
      const response = await fetch(`${API_BASE_URL}api/auth/verify-otp`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
      });

      const result = await response.json();
      console.log("OTP response:", result);

      if (result.status === "success") {
        localStorage.setItem("authToken", result.token);
        sessionStorage.removeItem("otpEmail");
        
        // Admin registration should always create admin users
        if (result.user?.role === 'admin') {
          console.log('✅ Admin account verified, redirecting to dashboard');
          window.location.href = "dashboard1.html";
        } else {
          console.log('⚠️ Account created but role is not admin:', result.user?.role);
          // Still redirect to dashboard as this is admin registration
          window.location.href = "dashboard1.html";
        }
      } else {
        showError(result.message || "Verification failed");
      }
    } catch (error) {
      console.error("OTP verification error:", error);
      showError("An error occurred during verification. Please try again.");
    } finally {
      // Hide loading overlay
      if (loadingOverlay) loadingOverlay.classList.add('d-none');
    }
  });
}

// =======================
// LOGIN
// =======================
if (loginForm) {
  loginForm.addEventListener("submit", async (e) => {
    e.preventDefault();

    const formData = new FormData(loginForm);
    const data = {
      email: formData.get("email"),
      password: formData.get("password"),
    };

    console.log("Admin login attempt:", { email: data.email });

    // Show loading overlay
    const loadingOverlay = document.getElementById('loadingOverlay');
    if (loadingOverlay) loadingOverlay.classList.remove('d-none');

    try {
      const response = await fetch(`${API_BASE_URL}api/auth/login`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
      });

      const result = await response.json();
      console.log("Login response:", result);

      if (result.status === "success") {
        localStorage.setItem("authToken", result.token);
        
        // Log user info and redirect to dashboard
        console.log('✅ Login successful, user role:', result.user?.role);
        console.log('✅ Redirecting to dashboard');
        window.location.href = "dashboard1.html";
      } else {
        showError(result.message || "Login failed");
      }
    } catch (error) {
      console.error("Admin login error:", error);
      showError("An error occurred during admin login. Please try again.");
    } finally {
      // Hide loading overlay
      if (loadingOverlay) loadingOverlay.classList.add('d-none');
    }
  });
}

// =======================
// RESEND OTP
// =======================
if (resendOtpBtn) {
  resendOtpBtn.addEventListener("click", async () => {
    if (!userEmail) {
      userEmail = sessionStorage.getItem("otpEmail") || "";
      if (!userEmail) {
        alert("Email not found. Please try signing up again.");
        return;
      }
    }

    console.log("Resending OTP to:", userEmail);

    try {
      const response = await fetch(`${API_BASE_URL}api/auth/resend-otp`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ email: userEmail }),
      });

      const result = await response.json();
      console.log("Resend OTP response:", result);

      if (result.status === "success") {
        alert("New OTP has been sent to your email");
        startResendTimer();
      } else {
        alert(result.message || "Failed to resend OTP");
      }
    } catch (error) {
      console.error("Error:", error);
      alert("An error occurred. Please try again.");
    }
  });
}

// =======================
// Timer for resend OTP
// =======================
function startResendTimer() {
  if (!resendOtpBtn) return;

  let timeLeft = 30;
  resendOtpBtn.disabled = true;

  const timer = setInterval(() => {
    timeLeft--;
    resendOtpBtn.textContent = `Resend (${timeLeft}s)`;

    if (timeLeft <= 0) {
      clearInterval(timer);
      resendOtpBtn.disabled = false;
      resendOtpBtn.textContent = "Resend";
    }
  }, 1000);
}

// =======================
// AUTH CHECK
// =======================
function checkAuth() {
  const token = localStorage.getItem("authToken");
  const currentPath = window.location.pathname;
  const isAuthPage =
    currentPath.includes("login.html") ||
    currentPath.includes("signup.html") ||
    currentPath.includes("otp.html") ||
    currentPath.includes("google-callback.html");

  console.log("Auth check:", { token: !!token, currentPath, isAuthPage });

  if (token && isAuthPage) {
    // Already logged in → send to dashboard
    console.log("✅ Token exists, redirecting from auth page to dashboard");
    window.location.href = "dashboard1.html";
  } else if (!token && !isAuthPage && currentPath.includes("/admin_dashboard/")) {
    // Not logged in and trying to access admin page → force login
    console.log("❌ No token, redirecting to login");
    window.location.href = "login.html";
  }
}

// Run on page load
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", checkAuth);
} else {
  checkAuth();
}

// =======================
// GOOGLE SIGN-IN
// =======================
const googleSignInBtn = document.getElementById('google-signin-btn');
if (googleSignInBtn) {
  googleSignInBtn.addEventListener('click', async function(e) {
    e.preventDefault();
    try {
      const response = await fetch(`${API_BASE_URL}api/auth/google`);
      const result = await response.json();
      if (result.status === 'success' && result.auth_url) {
        window.open(result.auth_url, '_blank', 'width=500,height=600');
      } else {
        alert('Could not get Google authorization URL.');
      }
    } catch (err) {
      alert('Error connecting to Google OAuth.');
    }
  });
}

// Handle Google OAuth callback
if (window.location.pathname.endsWith('google-callback.html')) {
  const urlParams = new URLSearchParams(window.location.search);
  const code = urlParams.get('code');
  if (code) {
    fetch(`${API_BASE_URL}api/auth/google/callback?code=${encodeURIComponent(code)}`)
      .then(res => res.json())
      .then(result => {
        if (result.status === 'success' && result.token) {
          localStorage.setItem('authToken', result.token);
          window.location.href = 'dashboard1.html';
        } else {
          // Show error in error display area if available
          if (window.showError) {
            window.showError(result.message || 'Google login failed.');
          } else {
            alert(result.message || 'Google login failed.');
          }
        }
      })
      .catch((err) => {
        if (window.showError) {
          window.showError('Error connecting to backend.');
        } else {
          alert('Error connecting to backend.');
        }
      });
  }
}
