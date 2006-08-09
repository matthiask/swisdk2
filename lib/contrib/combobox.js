function getFormElement(name, type){

	if(document.getElementsByName){

		var elements = document.getElementsByName(name);

		if(!type) return elements[0];

		for(var e = 0; e < elements.length; e++){
			if(elements[e].type == type) return elements[e];
		}

		return false;
	}

	for(var f = 0; f < document.forms.length; f++){
		for(var e = 0; e < document.forms[f].elements.length; e++){

			if(document.forms[f].elements[e].name != name) continue;

			if(!type) return document.forms[f].elements[e];

			if(document.forms[f].elements[e].type == type) return document.forms[f].elements[e];
		}
	}
}


//*****************************************************************************
// @author		Jan Marsch <jama@keks.com>
// @version		0.9 @ 2003-09-27 12:00
// @copyright	You might use and distribute this for free as long
//				as you keep this header notice.
//*****************************************************************************

function toCombo(selectName, buttonName){

	//****** Constructor ******************************************************
	//*************************************************************************

	//****** find & init Select element ******

	if(!selectName){
		window.status = "ComboBox Error: Select name required.";
		return false;
		}

	var selectObj = getFormElement(selectName, "select-one");

	if(selectObj == false){
		window.status = "ComboBox '"+selectName+"' Error: Select element not found.";
		return false;
	}

	this.selectObj = selectObj;

	//****** find & init Button element ******

	if(!buttonName){
		if(!document.createElement){
			window.status = "ComboBox '"+selectName+"' Error: Button name required.";
			return false;
		}
	}else{
		var buttonObj = getFormElement(buttonName, "button");

		if(buttonObj == false){
			if(!document.createElement){
				window.status = "ComboBox '"+selectName+"' Error: Button element not found.";
				return false;
			}
		}else{
			if(!document.createElement){
				this.buttonObj = buttonObj;
			}else{
				buttonObj.parentNode.removeChild(buttonObj);
			}
		}
	}

	//****** init Text element ******

	if(!document.createElement){
		this.textObj = new Object();
	}else{
		this.textObj = document.createElement("input");
		this.textObj.type = "text";
		this.textObj.name = selectName;
		this.textObj.id = selectName;

		if(this.selectObj.style.width) this.textObj.style.width = this.selectObj.style.width;
		if(this.selectObj.className)   this.textObj.className   = this.selectObj.className;

		this.textObj.autocomplete = "off";
	}

	//*********************************

	this.selectedIndex = -1;
	var handler = this;

	//*********************************

	if(!this.buttonObj){

		this.selectObj.onkeydown = function(e){
			var key = 0;

			if(!e) var e = window.event;

			if(e.keyCode){
				key = e.keyCode;
			}else{
				if(e.which) key = e.which;
			}

			switch(key){
				case 33:	// PAGE UP
				case 34:	// PAGE DOWN
				case 35:	// END
				case 36:	// HOME
				case 37:	// CURSOR LEFT
				case 38:	// CURSOR UP
				case 39:	// CURSOR RIGHT
				case 40:	// CURSOR DOWN
				case 27:	// ESCAPE
					return;

				default:
					handler.textMode();
			}
		}
	}

	//*********************************

	if(!this.buttonObj){

		this.textObj.onkeyup = function(e){
			var key = 0;

			if(!e) var e = window.event;

			if(e.keyCode){
				key = e.keyCode;
			}else{
				if(e.which) key = e.which;
			}

			switch(key){
				case  8:	// BACKSPACE
				case 33:	// PAGE UP
				case 34:	// PAGE DOWN
				case 35:	// END
				case 36:	// HOME
				case 37:	// CURSOR LEFT
				case 38:	// CURSOR UP
				case 39:	// CURSOR RIGHT
				case 40:	// CURSOR DOWN
				case 45:	// INSERT
				case 46:	// DELETE
					return;

				case 27:	// ESCAPE
					handler.selectMode(true);
					return;

				case  9:	// TAB (?)
				case 13:	// RETURN
					handler.selectMode();
					return;

				default:
					for(var i = 0; i < handler.selectObj.options.length; i++){
						if(handler.selectObj.options[i].text.toLowerCase().indexOf(this.value.toLowerCase()) != 0) continue;

						handler.selectedIndex = i;

						if(this.value.length == handler.selectObj.options[i].text.length) return;

						var input  = this.value;

						if(!this.setSelectionRange && !this.createTextRange) return;

						this.value = handler.selectObj.options[i].text;

						if(this.setSelectionRange){
							this.setSelectionRange(input.length, this.value.length);
							return;
						}

						var range = this.createTextRange()
						range.moveStart("character", input.length)
						range.select()
						return;
					}

					handler.selectedIndex = -1;
					return;
			}
		}
	}

	//*********************************

	if(!this.buttonObj){

		this.textObj.onblur = function(event){
			handler.selectMode();
		}
	}

	//*********************************

	if(this.buttonObj){

		this.buttonObj.onclick = function(){

			handler.textObj.value = prompt("", (handler.selectObj.options[0].text == " ") ? "" : handler.selectObj.options[0].text);

			if(handler.textObj.value == null || handler.textObj.value == ""){
				handler.selectMode(true);
				return;
			}

			for(var i = 0; i < handler.selectObj.options.length; i++){
				if(handler.selectObj.options[i].text.toLowerCase().indexOf(handler.textObj.value.toLowerCase()) != 0) continue;

				handler.selectedIndex = i;

				if(handler.textObj.value.length == handler.selectObj.options[i].text.length){
					handler.selectMode();
					return;
				}
			}

			handler.selectedIndex = -1;
			handler.selectMode();
			return;
		}
	}

	//*********************************

	this.selectMode = function(cancel){

		if(!this.buttonObj){
			this.textObj.parentNode.insertBefore(this.selectObj, this.textObj.nextSibling);
			this.textObj.parentNode.removeChild(this.textObj);
		}

		if(!cancel){
			if(this.selectedIndex == -1){
				this.selectObj.options[0].value = this.textObj.value;
				this.selectObj.options[0].text  = (this.textObj.value == "") ? " " : this.textObj.value;

				this.selectObj.selectedIndex = 0;
			}else{
				this.selectObj.selectedIndex = this.selectedIndex;
			}

			this.textObj.value = "";
		}

		this.selectObj.focus();
	}

	//*********************************

	this.textMode = function(cancel){

		this.selectObj.parentNode.insertBefore(this.textObj, this.selectObj.nextSibling);
		this.selectObj.parentNode.removeChild(this.selectObj);

		this.selectedIndex = this.selectObj.selectedIndex;

		this.textObj.value = (this.selectObj.options[0].text == " ") ? "" : this.selectObj.options[0].text;

		this.textObj.select();
	}
}
