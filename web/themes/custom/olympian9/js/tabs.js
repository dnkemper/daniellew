function openView(evt, categoryName) {
  var i, tabcontent, tablinks;
  tabcontent = document.getElementsByClassName("tabcontent");
  for (i = 0; i < tabcontent.length; i++) {
    tabcontent[i].style.display = "none";
  }
  tablinks = document.getElementsByClassName("tablinks");
  for (i = 0; i < tablinks.length; i++) {
    tablinks[i].className = tablinks[i].className.replace(" active", "");
  }
  document.getElementById(categoryName).style.display = "block";
  evt.currentTarget.className += " active";
}

var activeTab = "filtered-card-container"; // Initialize the active tab variable

function openPage(pageName,elmnt,color) {
  var i, tabcontent, tablinks;
  pagecontent = document.getElementsByClassName("pagecontent");
  for (i = 0; i < pagecontent.length; i++) {
    pagecontent[i].style.display = "none";
  }
  tablinks = document.getElementsByClassName("peoplelink");
  for (i = 0; i < tablinks.length; i++) {
    tablinks[i].style.color = "";
  }
  document.getElementById(pageName).style.display = "block";
  elmnt.style.color = color;
}
  //  used for views that render both grid and list displays on the same page.
function gridFunction() {
  var element = document.body;
  element.classList.add("toggle-list-class");
  element.classList.remove("toggle-grid-class");
}

function listFunction() {
  var element = document.body;
  element.classList.add("toggle-grid-class");
  element.classList.remove("toggle-list-class");
}
function azFunction() {
  var element = document.body;
  element.classList.toggle("show-alpha-filter");
}
function dateFunction() {
  var element = document.body;
  element.classList.toggle("show-date-filter");
}
