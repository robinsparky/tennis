<?php
    //Button title
    if(empty($buttonTitle)) $buttonTitle = "Find an Entrant";

    //Container in which to search
    if(empty($container)) $container = "ul.eventSignup";
   
    // Target class name containing the text to search
    if(empty($target)) $target = ".entrantName";
?>
<style>
/* The Search Dialog  */
.searchDialog {
  display: none; /* Hidden by default */
  position: absolute;
  z-index: 999;
  left: 0px;
  top: 0px;
  width:400px;
  height:100px;
  overflow: auto; /* Enable scroll if needed */
  background-color: rgb(0,0,0); /* Fallback color */
  background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
  cursor: move;
}

/* The search text */
.search-content {
    background-color: #fefefe;
    margin: 10% 5% 10% 5%;
    padding: 3px;
    border: 3px solid black;
    width: 80%;
    cursor:text;
    float: left;
}

/* The Close Button */
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
    var buttonTitle = "<?php echo $buttonTitle;?>"
    console.log(`Container:${srchContainerSelector} Target:${srchTargetSelector} title:${buttonTitle}`);
    var srchContainer
    var searchDialogEl
    var srchCandidates
    function searchButtonClick(searchButton) {
        console.log("Search Dialog button fired!");
        console.log(searchDialogEl)
        //let searchDialogEl = document.querySelector(".searchDialog")
        searchDialogEl.style.display="block"

        let top = parseInt(searchButton.offsetTop) //- parseInt(searchButton.offsetHeight) * 4
        let left = parseInt(searchButton.offsetLeft) + parseInt(searchButton.offsetWidth) * 2
        let pos = { top: top, left: left };
        console.log(pos);
        searchDialogEl.style.top = `${top}px`
        searchDialogEl.style.left = `${left}px`
    }

    function searchClose() {
        searchDialogEl.querySelector(".search-content").innerText="";
        searchDialogEl.style.display = 'none';
            srchCandidates.forEach(function(element,index,array) {
                element.style.backgroundColor = '';
        })
    }
  
    function searchText(name) {
        console.log("searchText(%s)", name);
        //let $list = $(container).children(".entrantName")

    }

    function onMouseDrag({ movementX, movementY }) {
        //let searchDialogEl = document.querySelector(".searchDialog")
        let getContainerStyle = window.getComputedStyle(searchDialogEl);
        let leftValue = parseInt(getContainerStyle.left);
        let topValue = parseInt(getContainerStyle.top);
        searchDialogEl.style.left = `${leftValue + movementX}px`;
        searchDialogEl.style.top = `${topValue + movementY}px`;
    }

    document.addEventListener("mousedown", () => {
        //let searchDialogEl = document.querySelector(".searchDialog")
        searchDialogEl.addEventListener("mousemove", onMouseDrag);
    });
    document.addEventListener("mouseup", () => {
        //let searchDialogEl = document.querySelector(".searchDialog")
        searchDialogEl.removeEventListener("mousemove", onMouseDrag);
    });

    /*
     * On Document Ready
    */
    document.addEventListener("DOMContentLoaded", function() {
        searchDialogEl = document.querySelector(".searchDialog")
        srchContainer = document.querySelector(srchContainerSelector)
        srchCandidates = srchContainer.querySelectorAll(srchTargetSelector)
        console.log("Search Candidates:")
        console.log(srchCandidates);

        document.querySelector('.search-content').addEventListener('input', function(event){
            let srchText = event.target.innerText.replaceAll(/[\n\f\r\t]/g,'');
            if(srchText.length > 1 ) {
                const regex = `.*${srchText}.*`;
                console.log(`Input Event: search text=${srchText} regex=${regex}`)
                let reobj = new RegExp(regex,"ig");
                let textVal = "";
                srchCandidates.forEach(function(element,index,array) {
                    if(element.tagName === 'INPUT') {textVal = element.value;}
                    else {textVal = element.innerText}
                    //console.log(`textVal=${textVal}`)

                    if(reobj.test(textVal)) {
                        console.log(`Found ${srchText} in ${textVal}`)
                        element.style.backgroundColor='red'
                    }
                    else {
                        element.style.backgroundColor=''
                    }
                });
                
            }
            else {
            srchCandidates.forEach(function(element,index,array) {
                element.style.backgroundColor = '';
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