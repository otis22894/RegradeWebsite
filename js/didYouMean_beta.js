
(function() {
    "use strict";

    // The didYouMean method.
    
    function escapeRegex( value ) {
        return value.replace( /[\-\[\]{}()*+?.,\\\^$|#\s]/g, "\\$&" );
    }



    function substringMatcher( array, term ) {
        var matcher = new RegExp( escapeRegex( term ), "i" );
        return $.grep( array, function( value ) {
            return matcher.test( value.label || value.value || value );
        });
    }


    function unique(array) {
        return $.grep(array, function(el, index) {
            return index === $.inArray(el, array);
        });
    } 
    
    function didYouMean(str, list, key) {
        if (!str) return null;

        // If we're running a case-insensitive search, smallify str.
        if (!didYouMean.caseSensitive) { str = str.toLowerCase(); }

        // Calculate the initial value (the threshold) if present.
        var thresholdRelative = didYouMean.threshold === null ? null : didYouMean.threshold * str.length,
            thresholdAbsolute = didYouMean.thresholdAbsolute,
            winningVal;
        if (thresholdRelative !== null && thresholdAbsolute !== null) winningVal = Math.min(thresholdRelative, thresholdAbsolute);
        else if (thresholdRelative !== null) winningVal = thresholdRelative;
        else if (thresholdAbsolute !== null) winningVal = thresholdAbsolute;
        else winningVal = null;

        // Get the edit distance to each option. If the closest one is less than 40% (by default) of str's length,
        // then return it.
        var winners = [], candidate, testCandidate, val,
            i, len = list.length;
        var winningVals = [null,null,null];
        var anyNull = true;
        for (i = 0; i < len; i++) {
            // Get item.
            candidate = list[i];
            // If there's a key, get the candidate value out of the object.
            if (key) { candidate = candidate[key]; }
            // Gatekeep.
            if (!candidate) { continue; }
            // If we're running a case-insensitive search, smallify the candidate.
            if (!didYouMean.caseSensitive) { testCandidate = candidate.toLowerCase(); }
            else { testCandidate = candidate; }
            // Get and compare edit distance.
            val = getEditDistance(str, testCandidate, winningVal);
            // If this value is smaller than our current winning value, OR if we have no winning val yet (i.e. the
            // threshold option is set to null, meaning the caller wants a match back no matter how bad it is), then
            // this is our new winner.
            if(anyNull){
                for(var i = 0;i<3;i++){
                    if(winningVals[i]==null){
                        winningVals[i] = val;
                        winners[i] = candidate;
                        break;
                    }
                }
                if(winningVals[0]!=null && winningVals[1]!=null && winningVals[2]!=null){
                    anyNull = false;
                }
            }
            if (val < Math.max.apply(this,winningVals)) {
                var winningPos = $.inArray(Math.max.apply(this,winningVals),winningVals);
                winningVals[winningPos] = val;
                winners[winningPos] = candidate
            }
        }

        // SORT 
        var A = winningVals;
        var B = winners;

        var all = [];

        for (var i = 0; i < B.length; i++) {
            all.push({ 'A': A[i], 'B': B[i] });
        }

        all.sort(function(a, b) {
            return a.A - b.A;
        });

        A = [];
        B = [];

        for (var i = 0; i < all.length; i++) {
            A.push(all[i].A);
            B.push(all[i].B);
        }
        
        var C = substringMatcher(list,str);
        
        C = C.concat(B);
        
        C = unique(C);

        return C;
    }  

    // Set default options.
    didYouMean.threshold = 0.4;
    didYouMean.thresholdAbsolute = 20;
    didYouMean.caseSensitive = false;
    didYouMean.nullResultValue = null;
    didYouMean.returnWinningObject = null;
    didYouMean.returnFirstMatch = false;

    // Expose.
    // In node...
    if (typeof module !== 'undefined' && module.exports) {
        module.exports = didYouMean;
    }
    // Otherwise...
    else {
        window.didYouMean = didYouMean;
    }

    var MAX_INT = Math.pow(2,32) - 1; // We could probably go higher than this, but for practical reasons let's not.
    function getEditDistance(a, b, max) {
        // Handle null or undefined max.
        max = max || max === 0 ? max : MAX_INT;

        var lena = a.length;
        var lenb = b.length;

        // Fast path - no A or B.
        if (lena === 0) return Math.min(max + 1, lenb);
        if (lenb === 0) return Math.min(max + 1, lena);

        // Fast path - length diff larger than max.
        if (Math.abs(lena - lenb) > max) return max + 1;

        // Slow path.
        var matrix = [],
            i, j, colMin, minJ, maxJ;

        // Set up the first row ([0, 1, 2, 3, etc]).
        for (i = 0; i <= lenb; i++) { matrix[i] = [i]; }

        // Set up the first column (same).
        for (j = 0; j <= lena; j++) { matrix[0][j] = j; }

        // Loop over the rest of the columns.
        for (i = 1; i <= lenb; i++) {
            colMin = MAX_INT;
            minJ = 1;
            if (i > max) minJ = i - max;
            maxJ = lenb + 1;
            if (maxJ > max + i) maxJ = max + i;
            // Loop over the rest of the rows.
            for (j = 1; j <= lena; j++) {
                // If j is out of bounds, just put a large value in the slot.
                if (j < minJ || j > maxJ) {
                    matrix[i][j] = max + 1;
                }

                // Otherwise do the normal Levenshtein thing.
                else {
                    // If the characters are the same, there's no change in edit distance.
                    if (b.charAt(i - 1) === a.charAt(j - 1)) {
                        matrix[i][j] = matrix[i - 1][j - 1];
                    }
                    // Otherwise, see if we're substituting, inserting or deleting.
                    else {
                        matrix[i][j] = Math.min(matrix[i - 1][j - 1] + 1, // Substitute
                                                Math.min(matrix[i][j - 1] + 1, // Insert
                                                         matrix[i - 1][j] + 1)); // Delete
                    }
                }

                // Either way, update colMin.
                if (matrix[i][j] < colMin) colMin = matrix[i][j];
            }

            // If this column's minimum is greater than the allowed maximum, there's no point
            // in going on with life.
            if (colMin > max) return max + 1;
        }
        // If we made it this far without running into the max, then return the final matrix value.
        return matrix[lenb][lena];
    }

})();