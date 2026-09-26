/*
*Displays row with default style of display:none
*/
function showElement(ID,tagName)
{
     obj = document.getElementsByTagName(tagName);
     for (var i=0; i<obj.length; i++)
     {
            if (obj[i].id == ID)
          obj[i].style.display = '';
     }
}
function hideElement(ID,tagName)
{
     obj = document.getElementsByTagName(tagName);
       for (var i=0; i<obj.length; i++)
     {
            if (obj[i].id == ID)
          obj[i].style.display = 'none';
     }
}
function StripSpaces(sdata) {

	// Takes spaces from front of string (Could do with replace?)
	sdata = sdata.replace(/^\s+/,"");
	sdata = sdata.replace(/\s+$/,"");
		
	sdata = ((sdata.indexOf(' ',0) == 0) && (sdata.length == 1)) ? '' : sdata;  
	
	return sdata;
}

function fieldRequired(fieldNameArray) {

	// Set field array
	var fieldArray = document.getElementsByName(fieldNameArray);
	// Strip spaces from the end
	var fieldValue = StripSpaces(fieldArray[0].value);
	// Check for empty
	return (fieldValue.length < 1);
}

function fieldString(fieldNameArray) {
	// Set the check variable to false to return if passes test
	var check = false;
	// Set field array
	var fieldArray = document.getElementsByName(fieldNameArray);
	// Strip spaces from the end
	var fieldValue = StripSpaces(fieldArray[0].value);
	
	return ((/[^a-z\s'-\.]/gi.test(fieldValue)) || fieldValue.length == 0);
}

function fieldAlphanumeric(fieldNameArray) {
	// Set the check variable to false to return if passes test
	var check = false;
	// Set field array
	var fieldArray = document.getElementsByName(fieldNameArray);
	
	return (/[\W]/gi.test(fieldArray[0].value));
}

function fieldDate(testType,fieldNameArray,dateFormat) {
	
	var fieldArray = document.getElementsByName(fieldNameArray);
	var fieldValue = (testType != "dt") ? fieldArray[0].value : fieldArray[0].value.split(" ")[0];
	var y;
	var m;
	var d;

	switch(dateFormat)
	{
		// Check field conforms to DMY format
		case "DMY":
			y = (fieldValue.split("/")[2]) ? fieldValue.split("/")[2] : fieldValue.split("-")[2];
			m = (fieldValue.split("/")[1]) - 1;						
			m = (m || m == 0) ? m : (fieldValue.split("-")[1]) - 1;					
			d = fieldValue.split("/")[0];
			d = (d != fieldValue) ? d : fieldValue.split("-")[0];						
			break;
		case "MDY":		
			y = fieldValue.split("/")[2];
			y = (y) ? y : fieldValue.split("-")[2];
			m = (fieldValue.split("/")[0]) - 1;
			m = (m || m == 0) ? m : (fieldValue.split("-")[0]) - 1;
			d = fieldValue.split("/")[1];
			d = (d) ? d : fieldValue.split("-")[0];
			break;
	}
	
	if (dateFormat == "DMY" || dateFormat == "MDY") {
		// Following determines the century
		var today = new Date();
		var yy = new String();
		yy = today.getFullYear() + '';
		y = (y > 50) ? (yy.substr(0,2) - 1) + y : yy.substr(0,2) + y;		
		// Perform valid date test
		with (new Date(y, m, d)) {			
	    	return !( getMonth() == m && getDate() == d && getFullYear() == y);
		}			
	}
	
	return false;	
}

function fieldTime(testType,fieldNameArray,timeFormat) {
	
	var fieldArray = document.getElementsByName(fieldNameArray);
	var fieldValue = (testType != "dt") ? fieldArray[0].value : fieldArray[0].value.split(" ")[1];
	
	if (timeFormat == "HHMM") {		
		return (fieldValue.split(":")[0] > 23 || fieldValue.split(":")[1] > 59);
	} else {
		return (fieldValue.split(":")[0] > 23 || fieldValue.split(":")[1] > 59 || fieldValue.split(":")[2] > 59);
	}
}
	
function fieldIP(fieldNameArray) {
	
 	// create reqular expression to validate that the
 	// format of the string is at least correct
 	var reg = /^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/;
 	var fieldArray = document.getElementsByName(fieldNameArray);

 	// test the input string against the regular expression
 	if (reg.test(fieldArray[0].value)) {

   		// now, validate the separate parts
   		var parts = fieldArray[0].value.split(".");
   		
   		return (parseInt(parseFloat(parts[0])) == 0);
   		
   		for (var i=0; i<parts.length; i++) {
     		return (parseInt(parseFloat(parts[i])) > 255);
   		}	
   		return false;
	} else {
   		return true;
 	}	
}

function fieldEmail(fieldNameArray) {

	// Set field array
	var fieldArray = document.getElementsByName(fieldNameArray);
	
	var fieldDataArray = fieldArray[0].value.split(",");	
	
	// Standard Email reg exp check
	var reg = /^([-!#$%&\'*+\\./0-9=?A-Z^_`a-z{|}~])+\@([-!#$%&\'*+\\/0-9=?A-Z^_`a-z{|}~])+\.([A-Za-z0-9\.-]{1,}?)$/;	

	var checkError = false;
	for (var i = 0; i < fieldDataArray.length; i++) {
		checkError = !(reg.test(fieldDataArray[i])); 
	}
	return checkError;
}

function fieldRegexp(fieldNameArray,regTest) {

	// Set field array
	var fieldArray = document.getElementsByName(fieldNameArray);

	var reg = new RegExp(regTest);

	// Standard Email reg exp check
	return !(reg.test(fieldArray[0].value));
}
function fieldCheck(errorHeader,testTypeArray,fieldNameArray,errorMsgArray,requiredMsgArray,requiredArray) {

	// errorHeader = Starting message on the error line
	// testTypeArray = Test to perform (Numeric, String....)
	// fieldNameArray = Name of field
	// errorMsgArray = Message to be displayed if field does not work
	// requiredMsgArray = Message array for required fields if set
	// requiredArray = boolean operator to determine if field is required
	var outError = new String();
	
	// Loop through the fields passed and then perform tests
	for (var i = 0; i < fieldNameArray.length; i++)
	{		
		// Check to see if field is required and if it is empty report the error
		if(requiredArray[i] && fieldRequired(fieldNameArray[i])){
			outError += '- ' + requiredMsgArray[i] + '\n';
		}else{
			
			if (document.getElementsByName(fieldNameArray[i])[0].value != '') 
			{			
				switch(testTypeArray[i].split(",")[0])
				{
					// Checks field has numerics only
					case "numeric":
						var tempArray = document.getElementsByName(fieldNameArray[i]);			
						if (isNaN(tempArray[0].value)) {
							outError += '- ' + errorMsgArray[i] + '\n';
						}
						break;
					// Checks field has Alphabetic, apostrophes, hyphens and full-stops
					case "string":			
						if (fieldString(fieldNameArray[i])) {
							outError += '- ' + errorMsgArray[i] + '\n';
						}
						break;
					// Checks field contains Alphanumerics only (Space, Alphabetics and
					// Numbers Only)
					case "alphanumeric":
						if (fieldAlphanumeric(fieldNameArray[i])) {
							outError += '- ' + errorMsgArray[i] + '\n';
						}
						break;
					case "date":			
						if (fieldDate("",fieldNameArray[i],testTypeArray[i].split(",")[1])) {
							outError += '- ' + errorMsgArray[i] + '\n';
						}
						break;
					case "time":						
						if (fieldTime("",fieldNameArray[i],testTypeArray[i].split(",")[1])) {
							outError += '- ' + errorMsgArray[i] + '\n';
						}
						break;
					case "datetime":			
						if (fieldDate("dt",fieldNameArray[i],testTypeArray[i].split(",")[1])  || fieldTime("dt",fieldNameArray[i],testTypeArray[i].split(",")[2])) {
							outError += '- ' + errorMsgArray[i] + '\n';
						}
						break;
					case "ip":
						if (fieldIP(fieldNameArray[i])) {
							outError += '- ' + errorMsgArray[i] + '\n';
						}
						break;
					case "email":
						if (fieldEmail(fieldNameArray[i])) {
							outError += '- ' + errorMsgArray[i] + '\n';
						}
						break;
					case "regexp":
						if (fieldRegexp(fieldNameArray[i],testTypeArray[i].split(",")[1])) {
							outError += '- ' + errorMsgArray[i] + '\n';
						}
						break;				
				}
			}
		}
	}
	
	if (outError.length > 0) {
		errorMsgArray = errorHeader + '\n\n' + outError;		
		alert(errorMsgArray);
		return false;
	} else {
		return true;
	}
}

function subMenuCheck(subMenuError,errorHeader,testTypeArray,fieldNameArray,errorMsgArray,requiredMsgArray,requiredArray){
	
	var fieldsOK = fieldCheck(errorHeader,testTypeArray,fieldNameArray,errorMsgArray,requiredMsgArray,requiredArray)
	if (!fieldsOK) {
		return false;
	}
	var fieldType = document.getElementsByName("field_type");
	var subMenuName = document.getElementsByName("sub_menu");
	if (fieldType[0].value == "subMenu" && subMenuName[0].value == "0") {
		alert(subMenuError);
		return false;
	}
	return true;
}

function urlParser(name){
	name = name.replace(/[\[]/,"\\\[").replace(/[\]]/,"\\\]");
	var regexString = "[\\?&]"+name+"=([^&#]*)";
	var regex = new RegExp(regexString);
	var regexAnswer = regex.exec(window.location.href);
	if (regexAnswer == null) {
		return "";
	} else {
		return regexAnswer[1];
	}
}
