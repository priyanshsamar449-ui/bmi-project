document.addEventListener('DOMContentLoaded', function () {
    var heightInput   = document.getElementById('height');
    var feetInput     = document.getElementById('feet');
    var inchesInput   = document.getElementById('inches');
    var weightInput   = document.getElementById('weight');
    var preview       = document.getElementById('livePreview');
    var form          = document.getElementById('bmiForm');

    var unitCmBtn     = document.getElementById('unitCmBtn');
    var unitFtBtn     = document.getElementById('unitFtBtn');
    var heightUnitBox = document.getElementById('height_unit');
    var cmFields      = document.getElementById('cmFields');
    var ftFields      = document.getElementById('ftFields');

    // Switches which height inputs are visible/required, and remembers the
    // choice in the hidden "height_unit" field so calculate.php knows which
    // one to read on the server side.
    function setHeightUnit(unit) {
        heightUnitBox.value = unit;

        if (unit === 'ft') {
            cmFields.style.display = 'none';
            ftFields.style.display = 'flex';
            heightInput.required = false;
            feetInput.required = true;
            inchesInput.required = true;
            unitFtBtn.classList.add('active');
            unitCmBtn.classList.remove('active');
        } else {
            cmFields.style.display = 'block';
            ftFields.style.display = 'none';
            heightInput.required = true;
            feetInput.required = false;
            inchesInput.required = false;
            unitCmBtn.classList.add('active');
            unitFtBtn.classList.remove('active');
        }
        updatePreview();
    }

    unitCmBtn.addEventListener('click', function () { setHeightUnit('cm'); });
    unitFtBtn.addEventListener('click', function () { setHeightUnit('ft'); });

    // Works out the height in cm no matter which unit is currently active,
    // so the live preview always matches what calculate.php will compute.
    function getHeightInCm() {
        if (heightUnitBox.value === 'ft') {
            var ft = parseFloat(feetInput.value) || 0;
            var inch = parseFloat(inchesInput.value) || 0;
            return (ft * 30.48) + (inch * 2.54);
        }
        return parseFloat(heightInput.value);
    }

    // Show an instant BMI preview as the user types, before they even submit.
    // The "real" value is still calculated and saved by PHP after submit.
    function updatePreview() {
        var h = getHeightInCm();
        var w = parseFloat(weightInput.value);

        if (h > 0 && w > 0) {
            var heightInMeters = h / 100;
            var bmi = w / (heightInMeters * heightInMeters);
            preview.textContent = 'Live preview: ' + bmi.toFixed(2);
        } else {
            preview.textContent = '\u00A0';
        }
    }

    heightInput.addEventListener('input', updatePreview);
    feetInput.addEventListener('input', updatePreview);
    inchesInput.addEventListener('input', updatePreview);
    weightInput.addEventListener('input', updatePreview);

    // Stop obviously bad input from being submitted
    form.addEventListener('submit', function (e) {
        var h = getHeightInCm();
        var w = parseFloat(weightInput.value);

        if (!h || !w || h <= 0 || w <= 0) {
            e.preventDefault();
            alert('Please enter a valid height and weight.');
        }
    });
});
