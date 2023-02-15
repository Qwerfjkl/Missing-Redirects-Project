 
 # delete specific redirects that sucked:
 delete from redirect_candidate where label = "1. Bundesliga";
 delete from redirect_candidate where label = "a massive brawl";
 delete from redirect_candidate where label = "area ruled";
 delete from redirect_candidate where label = "1-bbl";
 delete from redirect_candidate where label = "1(IA)";
 delete from redirect_candidate where label = "Twelver Shia Imams" and dest = "Shia Imam";
 delete from redirect_candidate where label = "twenty-sixth" and dest = "Twenty-sixth Amendment of the Constitution of Ireland";
 delete from redirect_candidate where label = "Two military coups" and dest = "Fiji coups of 1987";
 delete from redirect_candidate where label = "Unclassified" and dest = "Unclassified language";
 delete from redirect_candidate where label = "United States Senator, Missouri" and dest = "United States Senate";
 delete from redirect_candidate where label = "Vice Presidential" and dest = "Vice President of the United States";
 delete from redirect_candidate where label = "U-Z" and dest = "Country codes: U-Z";
 delete from redirect_candidate where label = "launched" and dest = "Ship naming and launching";
 delete from redirect_candidate where label = "local government district" and dest = "Districts of England";
 delete from redirect_candidate where label = "triples" and dest = "Triple (baseball)";
 delete from redirect_candidate where label = "Ranking" and dest = "List of languages by total speakers";
 delete from redirect_candidate where label = "batted" and dest = "Batting average";
 delete from redirect_candidate where label = "Full table" and dest = "Periodic table (standard)";
 delete from redirect_candidate where label = "hits" and dest = "Hit (baseball)";
 delete from redirect_candidate where label = "Entire network" and dest = "List of Melbourne railway stations";
 delete from redirect_candidate where label = "President of the Confederation" and dest = "List of Presidents of the Swiss Confederation";
 
 
 # don't link to:
 delete from redirect_candidate where dest = "Orders of magnitude (mass)";
 delete from redirect_candidate where dest = "Daylight saving time";
 delete from redirect_candidate where dest = "1 E0 m";
 delete from redirect_candidate where dest like "19% NHL season";
 delete from redirect_candidate where dest like "%*%";
 delete from redirect_candidate where dest like "Independence Day (movie)";
 delete from redirect_candidate where dest like "_";
 delete from redirect_candidate where dest like "__";
 delete from redirect_candidate where dest like "List of %";
 delete from redirect_candidate where dest like "% United States Congress";
 
 # don't link from:
 delete from redirect_candidate where label = "18th Government";
 delete from redirect_candidate where label = "(In detail)";
 delete from redirect_candidate where label = "(ISO 3166-2)";
 delete from redirect_candidate where label like "1 %";
 delete from redirect_candidate where label like "As of %";
 delete from redirect_candidate where label like "19__-__";
 delete from redirect_candidate where label like "1___ to 1___";
 delete from redirect_candidate where label like "1___-1___";
 delete from redirect_candidate where label like "Book _";
 delete from redirect_candidate where label like "(_-_)";
 delete from redirect_candidate where label like "_";
 delete from redirect_candidate where label like "__";
 delete from redirect_candidate where label like "% election%";
 delete from redirect_candidate where label = "In Detail";
 
 # never suggest links to self:
 delete from redirect_candidate where label = dest;
 
