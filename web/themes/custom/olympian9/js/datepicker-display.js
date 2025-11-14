const CONSTANTS = {
  DOM_SELECTORS: {
    datePicker: "",
    datePickerPrevMonth: ".date_picker_prev_month",
    datePickerNextMonth: ".date_picker_next_month",
    datePickerMonthDays: ".date_picker_month_days",
    datePickerMonthDay: ".date_picker_month_day",
    datePickerYear: ".date_picker_year",
    datePickerMonthName: ".date_picker_month_name",
    datePickerDay: ".day"
  },
  DOM_STRINGS: {
    dataTime: "li[data-time]"
  },
  DUMMY_LI_FOR_EMPTY_DAYS: '<li class="day"></li>',
  DAY_MAP: {
    0: "Sun",
    1: "Mon",
    2: "Tue",
    3: "Wed",
    4: "Thu",
    5: "Fri",
    6: "Sat"
  },
  MONTH_MAP: {
    0: "JAN",
    1: "FEB",
    2: "MAR",
    3: "APR",
    4: "MAY",
    5: "JUN",
    6: "JUL",
    7: "AUG",
    8: "SEP",
    9: "OCT",
    10: "NOV",
    11: "DEC"
  }
};

const utils = (function () {
  function prefixDOMSelectorsWithPickerSelector(pickerSelector) {
    let DOM_SELECTORS = {};
    for (let selector in CONSTANTS.DOM_SELECTORS) {
      DOM_SELECTORS[
        selector
      ] = `${pickerSelector} ${CONSTANTS.DOM_SELECTORS[selector]}`.trim();
    }
    CONSTANTS.DOM_SELECTORS = DOM_SELECTORS;
  }

  function getDOMElements(DOMSelectors) {
    let DOMElements = {};
    for (let selector in DOMSelectors) {
      if (DOMSelectors.hasOwnProperty(selector)) {
        DOMElements[selector] = document.querySelector(DOMSelectors[selector]);
      }
    }
    return DOMElements;
  }

  function getDatePickerWeekDaysNameMarkUp() {
    return `
        <li>Sun</li>
        <li>Mon</li>
        <li>Tue</li>
        <li>Wed</li>
        <li>Thu</li>
        <li>Fri</li>
        <li>Sat</li>`;
  }

  function getDayMarkup(day = 1, isActive = false, time = null, isPast = false, isToday = false) {
    if (!time) {
      console.trace(`The time provided for getDayMarkup ${time} is invalid`);
    }
  
    // Define the classes to be added based on conditions
    let classes = 'day';
    if (isPast) {
      classes += ' past';
    }
    if (isToday) {
      classes += ' today';
    }
  
    return `
      <li class="${classes}" data-time="${time}">
        <button class="${isActive ? "active" : ""}">${day}</button>
      </li>`;
  }
  

  function getAllDays() {
    let days = document.querySelectorAll(CONSTANTS.DOM_SELECTORS.datePickerDay);
    return [...(days ?? [])];
  }

  function getDaySuffix(day) {
    switch (day) {
      case 1:
      case 21:
      case 31:
        return "st";
      case 2:
      case 22:
        return "nd";
      case 3:
      case 23:
        return "rd";
      default:
        return "th";
    }
  }

  return {
    prefixDOMSelectorsWithPickerSelector,
    getDOMElements,
    getDatePickerWeekDaysNameMarkUp,
    getDayMarkup,
    getAllDays,
    getDaySuffix
  };
})();

const model = (function () {
  const data = {
    currentDate: new Date(),
    selectedDate: new Date()
  };

  function setCurrentDate(newDate) {
    data.currentDate = newDate;
  }

  function setSelectedDate(newDate) {
    data.selectedDate = newDate;
  }

  function getCurrentDate() {
    return data.currentDate;
  }

  function getSelectedDate() {
    return data.selectedDate;
  }

  return { setCurrentDate, setSelectedDate, getCurrentDate, getSelectedDate };
})();

const view = (function (model, utils) {
  function removeDays() {
    const allDays = utils.getAllDays();
    allDays.forEach((day) => day.remove());
  }

  function fillEmptyDays(count) {
    const DOMElements = utils.getDOMElements(CONSTANTS.DOM_SELECTORS);
    for (let i = 0; i < count; i++) {
      DOMElements.datePickerMonthDays.insertAdjacentHTML(
        "beforeend",
        CONSTANTS.DUMMY_LI_FOR_EMPTY_DAYS
      );
    }
  }

  function fillDay(day, isActive = false, time) {
    const dayMarkUp = utils.getDayMarkup(day, isActive, time);
    const DOMElements = utils.getDOMElements(CONSTANTS.DOM_SELECTORS);
    DOMElements.datePickerMonthDays.insertAdjacentHTML("beforeend", dayMarkUp);
  }

  function fillCurrentMonth(string) {
    const DOMElements = utils.getDOMElements(CONSTANTS.DOM_SELECTORS);
    DOMElements.datePickerMonthName.textContent = string;
  }
  document.addEventListener("DOMContentLoaded", function () {
    const dateDisplay = document.getElementById("dateDisplay");
    const copyButton = document.getElementById("copyButton");

    // Attach a click event listener to the copy button
    copyButton.addEventListener("click", function () {
        // Get the content of the div
        const datetimeString = dateDisplay.textContent;

        // Convert the content to a JavaScript Date object
        const datetimeValue = new Date(datetimeString);

        // Check if the conversion was successful
        if (!isNaN(datetimeValue.getTime())) {
            // Copy the datetime value to the clipboard
            const tempInput = document.createElement("input");
            tempInput.value = datetimeString;
            document.body.appendChild(tempInput);
            tempInput.select();
            document.execCommand("copy");
            document.body.removeChild(tempInput);

            // You can also use datetimeValue in your application as needed
            console.log("Datetime copied to clipboard:", datetimeValue);
        } else {
            console.error("Invalid datetime format:", datetimeString);
        }
    });
});
// JavaScript code to extract and send the value to Drupal
const dateDisplay = document.getElementById("dateDisplay");
const datetimeString = dateDisplay.textContent;

// Send the value to Drupal using a fetch request
// const datetimeString = "your_datetime_string"; // Replace with your datetime string


  // function fillSelectedDate(month, date, year) {
  //   const DOMElements = utils.getDOMElements(CONSTANTS.DOM_SELECTORS);
  //   const formattedDate = `${CONSTANTS.MONTH_MAP[month]} ${date} ${year}`;
  //   DOMElements.datePickerMonthDay.textContent = formattedDate;
  
  //   // Render the date in the "dateDisplay" element
  //   const dateDisplayElement = document.getElementById('dateDisplay');
  //   if (dateDisplayElement) {
  //     dateDisplayElement.textContent = formattedDate;
  //   }

  // }




  var dateDisplayValue = document.getElementById('dateDisplay').textContent;

  Drupal.behaviors.myCustomBehavior = {
    attach: function (context) {
      Drupal.theme.setVariable('dateDisplay', dateDisplayValue);
    }
  };

  function fillSelectedDate(month, date, year) {
    const DOMElements = utils.getDOMElements(CONSTANTS.DOM_SELECTORS);
  
    // Format the date as "CCYYMMDD"
    const formattedDate = `${year}${(month + 1).toString().padStart(2, '0')}${date.toString().padStart(2, '0')}`;
  
    // Update the hidden field with the formatted date
    DOMElements.datePickerMonthDay.textContent = formattedDate;
  
    // Render the date in the "dateDisplay" element
    const dateDisplayElement = document.getElementById('dateDisplay');
    if (dateDisplayElement) {
      dateDisplayElement.textContent = formattedDate;
  
      // Find the next sibling element (in this case, the paragraph)
      const nextSibling = dateDisplayElement.nextElementSibling;
  
      // Check if there is a next sibling element
      if (nextSibling) {
        // Define the new content
        const newContent = formattedDate; // Replace with your actual content
  
        // Update the content of the next sibling element
        nextSibling.textContent = newContent;
      }
    }
  }
  
  
  
  

  return {
    removeDays,
    fillEmptyDays,
    fillDay,
    fillCurrentMonth,
    fillSelectedDate
  };
})(model, utils);

const controller = (function (model, view, utils) {
  let DOMElements = null;

  function init(pickerSelector = "", selectedDate = new Date()) {
    utils.prefixDOMSelectorsWithPickerSelector(pickerSelector);
    DOMElements = utils.getDOMElements(CONSTANTS.DOM_SELECTORS);
    if (!DOMElements.datePicker) {
      throw new Error(
        `Date Picker with selector ${pickerSelector} not found in the document`
      );
    }
    DOMElements.datePickerNextMonth.addEventListener(
      "click",
      handleNextMonthClick
    );
    DOMElements.datePickerPrevMonth.addEventListener(
      "click",
      handlePrevMonthClick
    );
    DOMElements.datePickerMonthDays.addEventListener("click", handleSelectDate);
    if (selectedDate.constructor !== Date) {
      throw new Error(`The initial date ${selectedDate} is not a Date Object`);
    }
    let clonedSelectedDate = new Date(selectedDate.getTime());
    let clonedCurrentDate = new Date(selectedDate.getTime());
    model.setSelectedDate(clonedSelectedDate);
    model.setCurrentDate(clonedCurrentDate);
    render(selectedDate);
  }

  function handleSelectDate(event) {
    const time = event.target.closest(CONSTANTS.DOM_STRINGS.dataTime)?.dataset.time;
    if (!time) return;
  
    const selected = model.getSelectedDate();
    const clickedDate = new Date(Number(time));
  
    // Check if the clicked date is the same as the currently selected date
    if (
      selected &&
      selected.getDate() === clickedDate.getDate() &&
      selected.getMonth() === clickedDate.getMonth() &&
      selected.getFullYear() === clickedDate.getFullYear()
    ) {
      // If they are the same, clear the selection and the hash
      model.setSelectedDate(null);
      window.location.hash = '';
    } else {
      // Otherwise, set the selected date to the clicked date
      model.setSelectedDate(clickedDate);
  
      // Push hash value based on the selected date
      const hashValue = `${clickedDate.getFullYear()}${clickedDate.getMonth() + 1}${clickedDate.getDate()}`;
      window.location.hash = hashValue;
    }
  
    model.setCurrentDate(clickedDate);
    render();
  }

  function handleNextMonthClick() {
    render();
  }

  function handlePrevMonthClick() {
    let currentDate = new Date(model.getCurrentDate().getTime());
    currentDate.setMonth(currentDate.getMonth() - 2);
    model.setCurrentDate(currentDate);
    render();
  }

  function render(selectedDate = null) {
    updateSelectedDateMarkUp();
    view.removeDays();
    let currentDate = new Date(
      selectedDate?.getTime() ?? model.getCurrentDate().getTime()
    );
    let selected = model.getSelectedDate();
    let selectedDay = selected.getDate();
    let selectedMonth = selected.getMonth();
    let selectedYear = selected.getFullYear();
    let today = new Date(); // Get the current date
  
    currentDate.setDate(1);
    let renderingMonth = currentDate.getMonth();
    view.fillEmptyDays(currentDate.getDay());
    view.fillCurrentMonth(
      `${CONSTANTS.MONTH_MAP[renderingMonth]} - ${currentDate.getFullYear()}`
    );
  
    while (currentDate.getMonth() === renderingMonth) {
      let currentMonth = currentDate.getMonth();
      let currentDay = currentDate.getDate();
      let currentYear = currentDate.getFullYear();
      let isPast = currentDate < today;
  
      // Format the date strings for comparison
      let currentDateStr = `${currentYear}-${(currentMonth + 1)
        .toString()
        .padStart(2, '0')}-${currentDay.toString().padStart(2, '0')}`;
      let selectedDateStr = `${selectedYear}-${(selectedMonth + 1)
        .toString()
        .padStart(2, '0')}-${selectedDay.toString().padStart(2, '0')}`;
  
      // Add "past" class to past event dates
      // Add "today" class to today's date
      view.fillDay(
        currentDay,
        selectedDateStr === currentDateStr,
        currentDate.getTime(),
        isPast ? 'past' : currentDateStr === today.toISOString().split('T')[0] ? 'today' : ''
      );
  
      currentDate.setDate(currentDay + 1);
    }
    model.setCurrentDate(currentDate);
  }
  

  function updateSelectedDateMarkUp() {
    const currentDate = new Date(model.getSelectedDate().getTime());
    view.fillSelectedDate(
      currentDate.getMonth(),
      currentDate.getDate(),
      currentDate.getFullYear()
    );
  }
  // Define the getDynamicDateValue function to fetch the dynamic date value
function getDynamicDateValue() {
  // Replace this with your code to fetch the dynamic date value
  // For example, you can fetch it from an input field or an API
// Use the getDynamicDateValue function to update the dateDisplay variable
const newDateValue = getDynamicDateValue();
Drupal.theme.setVariable('dateDisplay', newDateValue);
}



// You may need to refresh or re-render parts of the template as needed
// to reflect the updated variable.


  return { init };
})(model, view, utils);

controller.init("#date_picker_1");
