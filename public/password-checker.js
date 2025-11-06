// validación de contraseñas con haveibeenpwned

// función para calcular SHA-1 (necesaria para HaveIBeenPwned)
async function sha1(str) {
    const buffer = new TextEncoder().encode(str);
    const hashBuffer = await crypto.subtle.digest('SHA-1', buffer);
    const hashArray = Array.from(new Uint8Array(hashBuffer));
    const hashHex = hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    return hashHex.toUpperCase();
}

// función para verificar contraseña en HaveIBeenPwned
async function checkPwnedPassword(password) {
    try {
        const hash = await sha1(password);
        const prefix = hash.substring(0, 5);
        const suffix = hash.substring(5);
        
        const response = await fetch(`https://api.pwnedpasswords.com/range/${prefix}`);
        if (!response.ok) {
            return { pwned: false, count: 0, error: true };
        }
        
        const text = await response.text();
        const lines = text.split('\r\n');
        
        for (const line of lines) {
            const [lineSuffix, count] = line.split(':');
            if (lineSuffix === suffix) {
                return { pwned: true, count: parseInt(count), error: false };
            }
        }
        
        return { pwned: false, count: 0, error: false };
    } catch (error) {
        console.error('Error checking password:', error);
        return { pwned: false, count: 0, error: true };
    }
}

// función para calcular fuerza de contraseña
function calculatePasswordStrength(password) {
    let strength = 0;
    
    if (password.length >= 10) strength += 1;
    if (password.length >= 15) strength += 1;
    if (/[a-z]/.test(password)) strength += 1;
    if (/[A-Z]/.test(password)) strength += 1;
    if (/[0-9]/.test(password)) strength += 1;
    if (/[^a-zA-Z0-9]/.test(password)) strength += 1;
    
    return strength;
}

// función para mostrar indicador de fuerza
function showStrengthIndicator(strength) {
    const strengthDiv = document.getElementById('password-strength');
    
    if (strength <= 2) {
        strengthDiv.innerHTML = '<span style="color: red;">❌ Contrasenya feble</span>';
    } else if (strength <= 4) {
        strengthDiv.innerHTML = '<span style="color: orange;">⚠️ Contrasenya mitjana</span>';
    } else {
        strengthDiv.innerHTML = '<span style="color: green;">✓ Contrasenya forta</span>';
    }
}

// debounce para no hacer demasiadas peticiones
let debounceTimer;
function debounce(func, delay) {
    return function(...args) {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => func.apply(this, args), delay);
    };
}

// inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    const passwordInput = document.getElementById('register_password');
    const submitBtn = document.getElementById('submit-btn');
    const pwnedWarning = document.getElementById('password-pwned-warning');
    
    if (!passwordInput) return;
    
    let isPwned = false;
    
    // verificar contraseña en tiempo real (con debounce de 500ms)
    const checkPassword = debounce(async function() {
        const password = passwordInput.value;
        
        if (password.length < 10) {
            showStrengthIndicator(0);
            pwnedWarning.style.display = 'none';
            isPwned = false;
            return;
        }
        
        // mostrar indicador de fuerza
        const strength = calculatePasswordStrength(password);
        showStrengthIndicator(strength);
        
        // verificar en HaveIBeenPwned
        submitBtn.disabled = true;
        submitBtn.value = 'Verificant...';
        
        const result = await checkPwnedPassword(password);
        
        if (result.pwned) {
            pwnedWarning.style.display = 'block';
            pwnedWarning.innerHTML = `⚠️ Aquesta contrasenya ha estat compromesa en ${result.count.toLocaleString()} filtracions de dades`;
            isPwned = true;
        } else {
            pwnedWarning.style.display = 'none';
            isPwned = false;
        }
        
        submitBtn.disabled = false;
        submitBtn.value = 'Crear compte';
    }, 500);
    
    // escuchar cambios en el input
    passwordInput.addEventListener('input', checkPassword);
    
    // validar antes de enviar el formulario
    const form = passwordInput.closest('form');
    form.addEventListener('submit', function(e) {
        if (isPwned) {
            const confirm = window.confirm('Aquesta contrasenya ha estat compromesa. Estàs segur que vols continuar?');
            if (!confirm) {
                e.preventDefault();
            }
        }
    });
});