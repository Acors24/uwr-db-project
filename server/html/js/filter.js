const field = document.getElementById("input_filter");
const trs = document.querySelectorAll("tbody > tr");
const options = document.querySelectorAll("select > option");

field.addEventListener("keyup", (event) => {
    console.log(field.value);
    const term = field.value.toLowerCase();
    for (const element of trs) {
        if (!element.innerHTML.toLowerCase().includes(term)) {
            newDisplay = "none";
        } else {
            newDisplay = "table-row";
        }
        element.style.display = newDisplay;
    }
    for (const element of options) {
        if (!element.innerHTML.toLowerCase().includes(term)) {
            newDisplay = "none";
        } else {
            newDisplay = "block";
        }
        element.style.display = newDisplay;
    }
});