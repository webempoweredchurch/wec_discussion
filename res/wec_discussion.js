var prevMsgFormID = "ReplyForm0";
var lastMsgNum = 0;

function moveReplyForm(newNum) {
	newFormID = "ReplyForm"+newNum;
	if (rFormNew = document.getElementById(newFormID)) {
		if (prevMsgFormID && (rFormPrev = document.getElementById(prevMsgFormID)) && (rFormPrev != rFormNew)) {
			// does not work with RTE, but allow on normal forms
			if (typeof(window['RTEarea']) == "undefined") {
				rFormNew.innerHTML = rFormPrev.innerHTML;
				while (rFormPrev.firstChild) {
					rFormPrev.removeChild(rFormPrev.firstChild);
				}
			}
		}
		prevMsgFormID = newFormID;
	}
}

function showHideMsg(num) {
	cItem = document.getElementById(num);
	if (cItem.style.display=="none")
		cItem.style.display="block";
	else
		cItem.style.display="none";
	return false;
}
function showHideMsgInline(num) {
	cItem = document.getElementById(num);
	if (cItem.style.display=="none")
		cItem.style.display="inline";
	else
		cItem.style.display="none";
	return false;
}
function forceHideMsg(num) {
	cItem = document.getElementById(num);
	if (cItem)
		cItem.style.display="none";
	return false;
}
function forceShowMsg(num) {
	cItem = document.getElementById(num);
	if (cItem)
		cItem.style.display="block";
	return false;
}
function showHideItem(num) {
	tItem = document.getElementById(num);
	if (tItem.style.display=="none")
		tItem.style.display="inline";
	else
		tItem.style.display="none";
	return false;
}
function showOneHideAllMsg(mNum,mTopLevel,mStr) {
	mNumVal = mNum.substring(3);

	// hide the last msg
	if (lastMsgNum) {
		forceHideMsg(lastMsgNum);
	}

	// handle clicking on will toggle on/off
	if (lastMsgNum == mNum) {
		cItem = document.getElementById(mNum);
		if (cItem) {
			if (cItem.style.display == "none")
				moveReplyForm(0);
			else
				moveReplyForm(mNumVal);
			lastMsgNum = 0;
			return false;
		}
	}
	// show the new message
	forceShowMsg(mNum);
	// show the comments
	forceShowMsg("CMMT"+mNumVal);

	makeReply(mNumVal,mTopLevel,mStr);
	lastMsgNum = mNum;
	location.hash = "msganchor"+mNumVal;
	return false;
}
