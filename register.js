const email = document.getElementById("email");

email.addEventListener("input", function () {

    if (email.value === "") {
        email.setCustomValidity("");
        return;
    }

    if (!email.value.toLowerCase().endsWith("@ncst.edu.ph")) {

        email.setCustomValidity(
            "Please use your NCST school email (@ncst.edu.ph)."
        );

    } else {

        email.setCustomValidity("");

    }

});