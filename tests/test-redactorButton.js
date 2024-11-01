

describe("Redactor Button Test Suite", function() {
	it("Null content to shortcodes2Html function should return an empty string", function() {
		expect(redactorHelper.shortcodes2Html(null)).toBe("");
	});
	
	it("Convert shortcodes to HTML with empty shortcode attributes", function() {
		expect(redactorHelper.shortcodes2Html("[redact]hello world[/redact]")).toBe("<span class='allowed' title='redact'>hello world</span>");
	});
	
	it("Convert shortcodes to HTML with just redactor attribute", function() {
		expect(redactorHelper.shortcodes2Html("[redact redactor='david']hello world[/redact]")).toBe("<span class='allowed' title='redact redactor=|david|'>hello world</span>");
	});
	
	it("Convert shortcodes to HTML with redactor and date attributes", function() {
		expect(redactorHelper.shortcodes2Html("[redact redactor='david' date='04/04/2016']hello world[/redact]"))
		.toBe("<span class='allowed' title='redact redactor=|david| date=|04/04/2016|'>hello world</span>");
	});
	
	it("Convert shortcodes to HTML with all attributes", function() {
		expect(redactorHelper.shortcodes2Html("[redact redactor='david' date='04/04/2016' allowed='role1, role2']hello world[/redact]"))
		.toBe("<span class='allowed' title='redact redactor=|david| date=|04/04/2016| allowed=|role1, role2|'>hello world</span>");
	});
	
	it("Convert html to shortcodes should not fail if given null", function() {
		expect(redactorHelper.html2Shortcodes(null)).toBe("");
	});
	
	it("Convert html to shortcodes with no attributes", function() {
		expect(redactorHelper.html2Shortcodes("<span class=\"allowed\" title=\"redact\">hello world</span>")).toBe("[redact]hello world[/redact]");
	});
	
	it("Convert html to shortcodes with only the redactor attribute.", function() {
		expect(redactorHelper.html2Shortcodes("<span class=\"allowed\" title=\"redact redactor=|david|\">hello world</span>")).toBe("[redact redactor='david']hello world[/redact]");
	});
	
	it("Convert html to shortcodes with the redactor and date attributes.", function() {
		expect(redactorHelper.html2Shortcodes("<span class=\"allowed\" title=\"redact date=|04/04/2016| redactor=|david|\">hello world</span>"))
		.toBe("[redact date='04/04/2016' redactor='david']hello world[/redact]");
	});
	
	it("Convert html to shortcodes with all attributes.", function() {
		expect(redactorHelper.html2Shortcodes("<span class=\"allowed\" title=\"redact date=|04/04/2016| redactor=|david| allowed=|role1, role2|\">hello world</span>"))
		.toBe("[redact date='04/04/2016' redactor='david' allowed='role1, role2']hello world[/redact]");
	});
	
	it("Replace node with first child of node", function() {
		var p = document.createElement('p');
		p.innerHTML = "<span>hello world</span>";
		redactorHelper.replaceNodeWithFirstChild(p.firstChild);
		expect(p.innerHTML).toBe("hello world");
	});
	
	it("Replace node function should not fail when given a null node", function() {
		expect(redactorHelper.replaceNodeWithFirstChild(null)).toBe("");
	});
});

