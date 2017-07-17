<?php
namespace Poirot\Queue
{
    /**
     * Port From Java Code From:
     *
     * Author: Keith Schwarz (htiek@cs.stanford.edu)
     *
     * An implementation of the alias method implemented using Vose's algorithm.
     * The alias method allows for efficient sampling of random values from a
     * discrete probability distribution (i.e. rolling a loaded die) in O(1) time
     * each after O(n) preprocessing time.
     *
     * For a complete writeup on the alias method, including the intuition and
     * important proofs, please see the article "Darts, Dice, and Coins: Smpling
     * from a Discrete Distribution" at
     *
     * http://www.keithschwarz.com/darts-dice-coins/
     *
     * ```php
     * $picks = [];
     * for ($i = 0; $i<=100; $i++) {
     *   $p = mathAlias( ['severA' => 0.1, 'serverB' => 0.8, 'serverC' => 0.2, 'serverD' => 0.3] );
     *   $picks[$p] = ( (isset($picks[$p])) ? $picks[$p] : 0 ) + 1;
     * }
     * ksort($picks);
     *
     * print_r($picks);
     * // Array ( [serverB] => 41 [serverC] => 19 [serverD] => 30 [severA] => 11 )
     * ```
     *
     * @param array $probabilities
     *
     * @return string
     */
    function mathAlias(array $probabilities)
    {
        $keys          = array_keys($probabilities);
        $probabilities = array_values($probabilities);


        $small = $large = [];

        $avg = 1.0 / count($probabilities);
        foreach ($probabilities as $i => $p)
            ($p >= $avg) ? $large[] = $i : $small[] = $i;

        $probability = [];
        while ( !empty($small) && !empty($large) ) {
            $less = array_pop($small);
            $more = array_pop($large);

            /* These probabilities have not yet been scaled up to be such that
             * 1/n is given weight 1.0.  We do this here instead.
             */
            $probability[$less]   = $probabilities[$less] * count($probabilities);
            $alias[$less]         = $more;

            $probabilities[$more] = ( $probabilities[$more] + $probabilities[$less] ) - $avg;

            /* If the new probability is less than the average, add it into the
             * small list; otherwise add it to the large list.
             */
            if ( $probabilities[$more] >= 1.0 / count($probabilities) )
                $large[] = $more;
            else
                $small[] = $more;
        }

        /* At this point, everything is in one list, which means that the
         * remaining probabilities should all be 1/n.  Based on this, set them
         * appropriately.  Due to numerical issues, we can't be sure which
         * stack will hold the entries, so we empty both.
         */
        while (! empty($small) )
            $probability[array_pop($small)] = 1.0;
        while (! empty($large) )
            $probability[array_pop($large)] = 1.0;


        $column   = mt_rand(0, count($probability) - 1 );
        $coinToss = ( ( (float) mt_rand() / (float) mt_getrandmax() ) < $probability[$column] );

        /* Based on the outcome, return either the column or its alias. */
        $column = $coinToss ? $column : $alias[$column];
        return $keys[$column];
    }
}
