<?php
    //Button title
    if(empty($buttonTitle)) $buttonTitle = "Find Text";

    //Container in which to search
    if(empty($container)) $container = "body";
   
    // Target class name containing the text to search
    if(empty($target)) $target = "*";
?>
<style>
/* The Find/Search Dialog  */
.searchDialog {
  display: none; /* Hidden by default */
  position: sticky;
  z-index: 999;
  left: 0;
  top: 0;
  width:400px;
  height:100px;
  overflow: auto; /* Enable scroll if needed */
  background-color: rgb(0,0,0); /* Fallback color */
  background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
  cursor: move;
}

/* The Find/Search text */
.search-content {
    background-color: #fefefe;
    margin: 10% 5% 10% 5%;
    padding: 3px;
    border: 3px solid black;
    width: 80%;
    cursor:text;
    float: left;
}

/* The Search/Find Close Button */
.searchDialogClose {
    color: white;
    margin-top: 2px;
    margin-left: 2px;
    font-size: 20px;
    font-weight: bold;
    width: 5%;
    float: left;
}

.searchDialogClose:hover,
.searchDialogClose:focus {
  color: black;
  text-decoration: none;
  cursor: pointer;
}
</style>
<script>
    var srchContainerSelector="<?php echo $container;?>"
    var srchTargetSelector="<?php echo $target;?>"
    var srchButtonTitle = "<?php echo $buttonTitle;?>"
    console.log(`Container:${srchContainerSelector} Target:${srchTargetSelector} title:${srchButtonTitle}`);
    var srchContainer
    var srchDialog
    var srchCandidates
    //Open the search/find dialog
    function searchButtonClick(searchButton) {
        console.log("Search Dialog button fired!");
        srchDialog.style.display="block";
        document.getElementById("search-text").focus({focusVisible: true, preventScroll: false })

        let top = parseInt(searchButton.offsetTop) //- parseInt(searchButton.offsetHeight) * 1
        let left = parseInt(searchButton.offsetLeft) //+ parseInt(searchButton.offsetWidth) * 1

        srchDialog.style.top = `${top}px`
        srchDialog.style.left = `${left}px`
    }

    //Close the search/find dialog and remove highligts
    function searchClose() {
        srchDialog.querySelector(".search-content").innerText="";
        srchDialog.style.display = 'none';
            srchCandidates.forEach(function(element,index,array) {
                element.style.backgroundColor = '';
        })
    }

    //Action taken for mousemove event
    function onMouseDrag({ movementX, movementY }) {
        let getContainerStyle = window.getComputedStyle(srchDialog);
        let leftValue = parseInt(getContainerStyle.left);
        let topValue = parseInt(getContainerStyle.top);
        srchDialog.style.left = `${leftValue + movementX}px`;
        srchDialog.style.top = `${topValue + movementY}px`;
    }

    //Action taken on mousedown event
    document.addEventListener("mousedown", () => {
        srchDialog.addEventListener("mousemove", onMouseDrag);
    });
    //Action taken on mouseup event
    document.addEventListener("mouseup", () => {
        srchDialog.removeEventListener("mousemove", onMouseDrag);
    });

    /*
     * On Document Ready
    */
    document.addEventListener("DOMContentLoaded", function() {
        srchDialog = document.querySelector(".searchDialog")
        if(srchContainerSelector === "body") {
            srchContainer = document;
        }
        else {
            srchContainer = document.querySelector(srchContainerSelector)
        }
        srchCandidates = srchContainer.querySelectorAll(srchTargetSelector)

        //Listen for search text input
        document.querySelector('.search-content').addEventListener('input', function(event){
            let srchText = event.target.innerText.replaceAll(/[\n\f\r\t]/g,'');
            if(srchText.length > 1 ) {
                const regex = `.*${srchText}.*`;
                let reobj = new RegExp(regex,"i");
                let textVal = "";
                srchCandidates.forEach(function(element,index,array) {
                    if(element.tagName === 'INPUT') {textVal = element.value;}
                    else {textVal = element.innerText}

                    if(reobj.test(textVal)) {                        
                        if(element.style.backgroundColor === undefined ) {
                            element.setAttribute('tempColor', "")
                        }
                        else {
                            element.setAttribute('tempColor', element.style.backgroundColor);
                        }
                        element.style.backgroundColor='red'
                        element.scrollIntoView({ behavior: "smooth", block: "end", inline: "nearest" });
                    }
                    else {
                        element.style.backgroundColor='';
                        if(element.hasAttribute('tempColor')) {
                            element.style.backgroundColor=element.getAttribute('tempColor');
                            element.removeAttribute('tempColor')
                        }
                    }
                });
                
            }
            else {
            srchCandidates.forEach(function(element,index,array) {
                                    element.style.backgroundColor = '';
                                    if(element.hasAttribute('tempColor')) {
                                        element.style.backgroundColor=element.getAttribute('tempColor');
                                        element.removeAttribute('tempColor')
                                    }
                                })
            }
        });
    }); 

</script>
<!-- Search Dialog -->
<button class="button find" type="button" id="searchDialogButton" onclick="searchButtonClick(this)"><?php echo $buttonTitle;?></button>
<div id="searchelem" class="searchDialog">
    <div id="search-text" class="search-content" contenteditable=true></div>
    <div class="searchDialogClose" onclick="searchClose()">&times;</div>
</div>