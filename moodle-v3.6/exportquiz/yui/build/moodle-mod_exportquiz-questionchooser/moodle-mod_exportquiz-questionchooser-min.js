YUI.add("moodle-mod_exportquiz-questionchooser",function(e,t){var n={ADDNEWQUESTIONBUTTONS:'.menu [data-action="addquestion"]',CREATENEWQUESTION:"div.createnewquestion",CHOOSERDIALOGUE:"div.chooserdialoguebody",CHOOSERHEADER:"div.choosertitle"},r=function(){r.superclass.constructor.apply(this,arguments)};e.extend(r,M.core.chooserdialogue,{initializer:function(){e.one("body").delegate("click",this.display_dialogue,n.ADDNEWQUESTIONBUTTONS,this)},display_dialogue:function(t){t.preventDefault();var r=e.one(n.CREATENEWQUESTION+" "+n.CHOOSERDIALOGUE),i=e.one(n.CREATENEWQUESTION+" "+n.CHOOSERHEADER);this.container===null&&(this.setup_chooser_dialogue(r,i,{}),this.prepare_chooser());var s=e.QueryString.parse(t.currentTarget.get("search").substring(1)),o=this.container.one("form");this.parameters_to_hidden_input(s,o,"returnurl"),this.parameters_to_hidden_input(s,o,"cmid"),this.parameters_to_hidden_input(s,o,"category"),this.parameters_to_hidden_input(s,o,"addonpage"),this.parameters_to_hidden_input(s,o,"appendqnumstring"),this.display_chooser(t)},parameters_to_hidden_input:function(e,t,n){var r;e.hasOwnProperty(n)?r=e[n]:r="";var i=t.one("input[name="+n+"]");i||(i=t.appendChild('<input type="hidden">'),i.set("name",n)),i.set("value",r)}},{NAME:"mod_exportquiz-questionchooser"}),M.mod_exportquiz=M.mod_exportquiz||{},M.mod_exportquiz.init_questionchooser=function(){return M.mod_exportquiz.question_chooser=new r({}),M.mod_exportquiz.question_chooser}},"@VERSION@",{requires:["moodle-core-chooserdialogue","moodle-mod_exportquiz-util","querystring-parse"]});