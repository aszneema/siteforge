<?php
declare(strict_types=1);

namespace SiteForge;

/**
 * Copy bank for the offline builder.
 *
 * Each industry supplies real, specific marketing copy so a generated site
 * reads like a finished page rather than a template full of lorem ipsum.
 * "{name}" is replaced with the detected business name.
 */
final class Content
{
    /** Fields every industry inherits unless it overrides them. */
    private static function defaults(): array
    {
        return [
            'sections' => ['hero', 'stats', 'about', 'services', 'testimonials', 'cta', 'contact'],
            'pricing' => [
                ['name' => 'Starter', 'price' => '£290', 'note' => 'one-off',
                 'features' => ['Initial consultation', 'Single focused deliverable', 'Two rounds of revisions', 'Email support'],
                 'featured' => false],
                ['name' => 'Standard', 'price' => '£740', 'note' => 'per month',
                 'features' => ['Everything in Starter', 'Ongoing monthly work', 'Priority scheduling', 'Monthly progress report', 'Phone and email support'],
                 'featured' => true],
                ['name' => 'Complete', 'price' => 'Custom', 'note' => 'let\'s talk',
                 'features' => ['Everything in Standard', 'Dedicated account lead', 'Same-day response', 'Quarterly strategy review'],
                 'featured' => false],
            ],
            'team' => [
                ['name' => 'Amara Osei', 'role' => 'Founder & Director',
                 'bio' => 'Started the business in 2014 after a decade in the industry, and still handles every first consultation personally.'],
                ['name' => 'Daniel Reyes', 'role' => 'Operations Lead',
                 'bio' => 'Keeps schedules honest and standards high. If a deadline is going to move, you hear it from Daniel first.'],
                ['name' => 'Priya Nair', 'role' => 'Client Relations',
                 'bio' => 'Your first point of contact, and the reason most of our clients say they never chase us for an update.'],
            ],
            'faq' => [
                ['q' => 'How quickly can you start?',
                 'a' => 'Most new clients are booked in within a week. If something is urgent, say so when you get in touch and we will tell you honestly whether we can make it work.'],
                ['q' => 'What does it cost?',
                 'a' => 'It depends on scope, so we quote per project rather than guessing at a headline figure. You get a written quote before any work begins, and it does not move unless you change the brief.'],
                ['q' => 'Do you work with small budgets?',
                 'a' => 'Often, yes. Tell us the number you have in mind and we will tell you straight away whether we can do something worthwhile with it.'],
            ],
            'contact' => [
                'email' => 'hello@example.com',
                'phone' => '+44 20 7946 0182',
                'address' => '14 Fenwick Street, Manchester M1 4EX',
                'hours' => 'Mon – Fri, 9am – 6pm',
            ],
        ];
    }

    public static function all(): array
    {
        static $merged = null;
        if ($merged !== null) {
            return $merged;
        }

        $defaults = self::defaults();
        $merged = [];
        foreach (self::banks() as $key => $bank) {
            $merged[$key] = array_replace($defaults, $bank);
        }

        return $merged;
    }

    public static function industry(string $key): array
    {
        $all = self::all();
        return $all[$key] ?? $all['generic'];
    }

    private static function banks(): array
    {
        return [

        'restaurant' => [
            'match' => ['restaurant', 'bistro', 'trattoria', 'eatery', 'dining', 'chef', 'cuisine', 'pizzeria', 'steakhouse', 'sushi', 'menu', 'tapas', 'brasserie'],
            'name' => 'Olive & Ash', 'tagline' => 'Seasonal plates, cooked over fire', 'palette' => 'ember', 'style' => 'elegant',
            'sections' => ['hero', 'about', 'services', 'stats', 'gallery', 'testimonials', 'cta', 'contact'],
            'hero' => ['eyebrow' => 'Open Tuesday – Sunday', 'title' => 'Food worth slowing down for',
                       'sub' => '{name} is a neighbourhood kitchen built around a wood-fired grill, a short menu, and produce picked the same week it is served.',
                       'cta' => 'Book a table', 'cta2' => 'See the menu'],
            'about' => ['title' => 'A small kitchen with a long memory',
                        'lead' => 'One room, twelve tables, and a menu that changes when the growers tell us it should.',
                        'body' => ['{name} opened with a simple idea: cook a handful of things properly instead of a hundred things adequately. The menu is short on purpose. It changes every few weeks because the produce does.',
                                   'Almost everything is made in-house — the bread each morning, the pasta before service, the ice cream between lunch and dinner. Our suppliers are people we know by name, and most of them are within sixty miles.'],
                        'points' => ['Wood-fired grill at the heart of the kitchen', 'Menu rewritten with the seasons', 'Bread, pasta and desserts made in-house daily']],
            'services' => ['title' => 'What we serve', 'sub' => 'A short menu, changed often, cooked to order.',
                'items' => [
                    ['title' => 'Small plates', 'text' => 'Six or seven things to start — cured fish, charred vegetables, whipped butter and warm bread straight from the oven.'],
                    ['title' => 'From the grill', 'text' => 'Whole fish, dry-aged cuts and thick-stemmed vegetables cooked over embers until they taste of smoke and nothing else.'],
                    ['title' => 'Fresh pasta', 'text' => 'Rolled every afternoon. Two shapes, two sauces, changed whenever we find something better at the market.'],
                    ['title' => 'Private dining', 'text' => 'The back room seats fourteen. Set menu, one bill, and a kitchen that will happily work around what your table cannot eat.'],
                ]],
            'stats' => [['value' => '12', 'label' => 'Tables in the room'], ['value' => '9', 'label' => 'Years on this street'], ['value' => '60mi', 'label' => 'Furthest supplier']],
            'testimonials' => [
                ['quote' => 'We came for a birthday and have been back six times since. The menu is never quite the same, which is the whole point.', 'name' => 'Hannah Whitfield', 'role' => 'Regular since 2019'],
                ['quote' => 'The best plate of pasta I have eaten outside Italy, served by people who clearly enjoy their jobs.', 'name' => 'Marcus Bell', 'role' => 'Food writer'],
            ],
            'faq' => [
                ['q' => 'Do you take walk-ins?', 'a' => 'We hold four seats at the counter for walk-ins every night. Everything else is worth booking, especially Thursday to Saturday.'],
                ['q' => 'Can you cater for allergies?', 'a' => 'Yes. Tell us when you book and the kitchen will plan around it rather than improvising on the night.'],
                ['q' => 'Is there a set menu?', 'a' => 'À la carte most nights, plus a five-course tasting menu on Fridays and Saturdays for the whole table.'],
            ],
            'contact' => ['email' => 'hello@example.com', 'phone' => '+44 161 496 0210', 'address' => '8 Ashcroft Lane, Manchester M4 1HN', 'hours' => 'Tue – Sun, 5pm – late'],
        ],

        'cafe' => [
            'match' => ['cafe', 'café', 'coffee', 'bakery', 'espresso', 'roastery', 'patisserie', 'brunch', 'tea room'],
            'name' => 'Marlow & Bean', 'tagline' => 'Coffee roasted here, bread baked here', 'palette' => 'sand', 'style' => 'modern',
            'sections' => ['hero', 'about', 'services', 'stats', 'gallery', 'testimonials', 'cta', 'contact'],
            'hero' => ['eyebrow' => 'Open from 7am, every day', 'title' => 'Your morning, done properly',
                       'sub' => '{name} roasts its own coffee in the back and bakes the pastries out front. Come in early enough and you will smell both.',
                       'cta' => 'Find us', 'cta2' => 'Today\'s menu'],
            'about' => ['title' => 'Two ovens, one roaster, no shortcuts',
                        'lead' => 'A café built for people who care what is in the cup and what came out of the oven.',
                        'body' => ['{name} started as a market stall with a single grinder. The roaster came two years later, the bakery a year after that. Everything we sell is made within about ten metres of where you drink it.',
                                   'Beans are roasted in small weekly batches and rested properly before they hit the grinder. The pastry comes out of the oven three times a day, so what you get at four in the afternoon is not what has been sitting there since dawn.'],
                        'points' => ['Beans roasted weekly, on site', 'Pastry baked three times a day', 'Oat, soy and almond at no extra charge']],
            'services' => ['title' => 'What we make', 'sub' => 'Small menu. Made properly. Every day.',
                'items' => [
                    ['title' => 'Filter & espresso', 'text' => 'A rotating single origin on filter and a house blend on espresso, both roasted upstairs within the last ten days.'],
                    ['title' => 'Fresh bakery', 'text' => 'Croissants, cardamom buns and a sourdough loaf that sells out most days before noon.'],
                    ['title' => 'All-day brunch', 'text' => 'Eggs, toast and seasonal plates served until three, because not everyone gets up at seven.'],
                    ['title' => 'Beans to take home', 'text' => 'Retail bags roasted the same week, plus a subscription that lands on your doormat every fortnight.'],
                ]],
            'stats' => [['value' => '7am', 'label' => 'Doors open'], ['value' => '3x', 'label' => 'Daily bakes'], ['value' => '10d', 'label' => 'Max roast age']],
            'testimonials' => [
                ['quote' => 'The only place near the office where the coffee is consistently excellent and nobody rushes you out.', 'name' => 'Sara Duke', 'role' => 'Works around the corner'],
                ['quote' => 'I buy their beans for home now. Same cup, half the price, no queue.', 'name' => 'Tomasz Nowak', 'role' => 'Subscriber'],
            ],
            'contact' => ['email' => 'hello@example.com', 'phone' => '+44 113 496 0188', 'address' => '22 Kirkstall Road, Leeds LS3 1LN', 'hours' => 'Mon – Sun, 7am – 4pm'],
        ],

        'fitness' => [
            'match' => ['gym', 'fitness', 'personal train', 'workout', 'crossfit', 'pilates', 'yoga', 'strength', 'bootcamp', 'coaching studio'],
            'name' => 'Ironworks Strength', 'tagline' => 'Coaching that actually gets you stronger', 'palette' => 'crimson', 'style' => 'bold',
            'sections' => ['hero', 'stats', 'about', 'services', 'pricing', 'testimonials', 'faq', 'cta', 'contact'],
            'hero' => ['eyebrow' => 'Small group & 1-to-1 coaching', 'title' => 'Train with a plan, not a guess',
                       'sub' => '{name} is a coaching gym, not a treadmill warehouse. Every member gets a written programme and a coach who knows their name.',
                       'cta' => 'Book a free session', 'cta2' => 'See memberships'],
            'about' => ['title' => 'Coaching first, equipment second',
                        'lead' => 'We cap classes at eight so nobody trains unsupervised.',
                        'body' => ['{name} was built by coaches who got tired of watching people repeat the same three exercises for two years. Every member starts with a movement assessment and leaves with a programme written for their body and their schedule.',
                                   'Progress is tracked on paper, reviewed every six weeks, and adjusted when the numbers say so. If something is not working, we change it rather than telling you to try harder.'],
                        'points' => ['Classes capped at eight people', 'Written programme, reviewed every six weeks', 'Coaches qualified to Level 4 or above']],
            'services' => ['title' => 'How we train', 'sub' => 'Pick the format that fits your week.',
                'items' => [
                    ['title' => 'Small group strength', 'text' => 'Eight people, one coach, a barbell each. Structured blocks that build over twelve weeks rather than random daily workouts.'],
                    ['title' => 'One-to-one coaching', 'text' => 'Fully individual programming for a specific goal — a first pull-up, a return from injury, a competition platform.'],
                    ['title' => 'Online programming', 'text' => 'A written plan delivered to your phone, with video form checks and a coach who replies within a day.'],
                    ['title' => 'Return to movement', 'text' => 'Six-week block for anyone coming back after injury, surgery or a long time away from training.'],
                ]],
            'stats' => [['value' => '8', 'label' => 'Max class size'], ['value' => '340+', 'label' => 'Members coached'], ['value' => '6wk', 'label' => 'Programme reviews']],
            'testimonials' => [
                ['quote' => 'I deadlifted 60kg when I joined. Eighteen months later it is 120kg and my back has never felt better.', 'name' => 'Rachel Odubayo', 'role' => 'Member since 2023'],
                ['quote' => 'First gym where somebody actually watched what I was doing and corrected it.', 'name' => 'James Prior', 'role' => 'Small group member'],
            ],
            'pricing' => [
                ['name' => 'Group', 'price' => '£89', 'note' => 'per month', 'features' => ['Unlimited small group sessions', 'Written twelve-week programme', 'Six-weekly progress review', 'App access'], 'featured' => false],
                ['name' => 'Hybrid', 'price' => '£165', 'note' => 'per month', 'features' => ['Everything in Group', 'Two 1-to-1 sessions monthly', 'Individual programming', 'Nutrition check-ins'], 'featured' => true],
                ['name' => 'Private', 'price' => '£310', 'note' => 'per month', 'features' => ['Weekly 1-to-1 coaching', 'Fully bespoke programme', 'Direct coach messaging', 'Competition prep'], 'featured' => false],
            ],
            'faq' => [
                ['q' => 'I have never lifted before. Is this for me?', 'a' => 'Yes — about half our members had never touched a barbell before joining. Everyone starts with the same assessment and their own starting weights.'],
                ['q' => 'Is there a joining fee or contract?', 'a' => 'No joining fee, and memberships are rolling monthly. Cancel with thirty days\' notice.'],
                ['q' => 'What if I get injured?', 'a' => 'Tell your coach and the programme gets rewritten around it. We would rather change the plan than lose the habit.'],
            ],
            'contact' => ['email' => 'hello@example.com', 'phone' => '+44 121 496 0144', 'address' => 'Unit 3, Foundry Yard, Birmingham B5 6RQ', 'hours' => 'Mon – Fri 6am – 9pm, Sat 8am – 2pm'],
        ],

        'beauty' => [
            'match' => ['salon', 'barber', 'hair', 'spa', 'beauty', 'nails', 'aesthetic', 'massage', 'skincare', 'grooming'],
            'name' => 'Atelier Nine', 'tagline' => 'Considered cuts and quiet luxury', 'palette' => 'rose', 'style' => 'elegant',
            'sections' => ['hero', 'about', 'services', 'pricing', 'gallery', 'testimonials', 'cta', 'contact'],
            'hero' => ['eyebrow' => 'By appointment', 'title' => 'Time booked properly, work done properly',
                       'sub' => '{name} runs on long appointments and small guest lists, so nobody is rushed and nothing is compromised.',
                       'cta' => 'Book an appointment', 'cta2' => 'View services'],
            'about' => ['title' => 'A quieter kind of salon',
                        'lead' => 'Four chairs, unhurried appointments, and a consultation before every single service.',
                        'body' => ['{name} was opened by stylists who wanted longer with each guest. Appointments are booked generously on purpose — there is time to talk about what you actually want before anyone picks up a pair of scissors.',
                                   'Every service starts with a proper consultation and finishes with honest advice about how to keep it looking right at home. If what you are asking for will not work with your hair, we will say so and offer something that will.'],
                        'points' => ['Consultation before every service', 'Four chairs, never overbooked', 'Ammonia-free colour as standard']],
            'services' => ['title' => 'Services', 'sub' => 'Everything includes a consultation and a finish.',
                'items' => [
                    ['title' => 'Cut & finish', 'text' => 'A full ninety minutes: consultation, wash, precision cut and a blow-dry you could actually recreate yourself.'],
                    ['title' => 'Colour', 'text' => 'Balayage, full colour and corrective work using ammonia-free lines, always with a strand test first.'],
                    ['title' => 'Treatments', 'text' => 'Bond-building and scalp treatments for hair that has been coloured, heat-styled or generally put through it.'],
                    ['title' => 'Occasions', 'text' => 'Bridal and event styling, including a trial run so there are no surprises on the day.'],
                ]],
            'stats' => [['value' => '4', 'label' => 'Chairs'], ['value' => '90min', 'label' => 'Standard appointment'], ['value' => '2011', 'label' => 'Opened']],
            'testimonials' => [
                ['quote' => 'First time in years I have left a salon looking like the photo I brought in.', 'name' => 'Elena Marchetti', 'role' => 'Client'],
                ['quote' => 'They talked me out of the colour I asked for and were completely right. That is worth more than the discount anywhere else.', 'name' => 'Noor Haddad', 'role' => 'Client since 2021'],
            ],
            'pricing' => [
                ['name' => 'Cut & finish', 'price' => '£58', 'note' => '90 minutes', 'features' => ['Full consultation', 'Wash and scalp massage', 'Precision cut', 'Blow-dry and styling advice'], 'featured' => false],
                ['name' => 'Colour & cut', 'price' => '£145', 'note' => '3 hours', 'features' => ['Consultation and strand test', 'Ammonia-free colour', 'Bond-building treatment', 'Cut and finish'], 'featured' => true],
                ['name' => 'Bridal', 'price' => '£320', 'note' => 'trial + day', 'features' => ['Full trial appointment', 'Day-of styling', 'On-location option', 'Touch-up kit'], 'featured' => false],
            ],
            'contact' => ['email' => 'hello@example.com', 'phone' => '+44 20 7946 0311', 'address' => '9 Marchmont Street, London WC1N 1AP', 'hours' => 'Tue – Sat, 9am – 7pm'],
        ],

        'clinic' => [
            'match' => ['clinic', 'dental', 'dentist', 'medical', 'doctor', 'physio', 'therapy', 'chiropract', 'veterinar', 'health centre', 'optician', 'orthodont'],
            'name' => 'Parkside Health', 'tagline' => 'Careful, unhurried clinical care', 'palette' => 'ocean', 'style' => 'minimal',
            'sections' => ['hero', 'stats', 'about', 'services', 'team', 'faq', 'testimonials', 'cta', 'contact'],
            'hero' => ['eyebrow' => 'Accepting new patients', 'title' => 'Care that takes the time it needs',
                       'sub' => '{name} runs longer appointments than most practices, because rushed consultations are how things get missed.',
                       'cta' => 'Book an appointment', 'cta2' => 'Our services'],
            'about' => ['title' => 'A practice built around the appointment',
                        'lead' => 'Thirty-minute consultations as standard, and the same clinician each visit wherever possible.',
                        'body' => ['{name} has looked after this community since 2008. We deliberately book fewer patients per session than the average practice so that appointments start on time and run long enough to cover what you actually came in for.',
                                   'Continuity matters: we will always try to book you with the clinician who saw you last. Notes are written properly, referrals are chased, and you will never leave without knowing what happens next.'],
                        'points' => ['30-minute standard appointments', 'Same clinician wherever possible', 'Step-free access and on-site parking']],
            'services' => ['title' => 'What we treat', 'sub' => 'Routine care, urgent problems and long-term conditions.',
                'items' => [
                    ['title' => 'Routine check-ups', 'text' => 'Full examination, clear explanation of anything we find, and a written plan you can take away.'],
                    ['title' => 'Urgent appointments', 'text' => 'Same-day slots held back every morning for problems that will not wait until next week.'],
                    ['title' => 'Ongoing conditions', 'text' => 'Structured reviews for long-term conditions, with the same clinician managing your care throughout.'],
                    ['title' => 'Referrals', 'text' => 'When you need a specialist we make the referral, send the notes across, and follow up to make sure it landed.'],
                ]],
            'stats' => [['value' => '30min', 'label' => 'Standard appointment'], ['value' => '2008', 'label' => 'Serving the area since'], ['value' => '4,100', 'label' => 'Registered patients']],
            'testimonials' => [
                ['quote' => 'The first practice where I have not felt like I was being hurried out of the door.', 'name' => 'Margaret Ellis', 'role' => 'Patient'],
                ['quote' => 'They chased my hospital referral twice without me asking. That never happens.', 'name' => 'Dev Raghunathan', 'role' => 'Patient'],
            ],
            'faq' => [
                ['q' => 'Are you taking new patients?', 'a' => 'Yes. Registration takes about ten minutes online or at reception, and you can usually book your first appointment the same week.'],
                ['q' => 'What if I need to be seen today?', 'a' => 'We hold urgent slots back every morning. Call when we open at 8am and reception will triage you.'],
                ['q' => 'Is the building accessible?', 'a' => 'Yes — step-free entrance, an accessible WC, a ground-floor consulting room and four dedicated parking bays.'],
            ],
            'contact' => ['email' => 'hello@example.com', 'phone' => '+44 117 496 0455', 'address' => '31 Parkside Avenue, Bristol BS6 5NX', 'hours' => 'Mon – Fri, 8am – 6.30pm'],
        ],

        'professional' => [
            'match' => ['law', 'legal', 'solicitor', 'attorney', 'accountant', 'accounting', 'tax', 'consulting', 'consultancy', 'advisory', 'chartered', 'bookkeep'],
            'name' => 'Hartley & Crowe', 'tagline' => 'Straight advice, written in plain English', 'palette' => 'slate', 'style' => 'minimal',
            'sections' => ['hero', 'stats', 'about', 'services', 'team', 'faq', 'testimonials', 'cta', 'contact'],
            'hero' => ['eyebrow' => 'Fixed fees, agreed up front', 'title' => 'Advice you can act on',
                       'sub' => '{name} gives you the answer, the risk and the cost in plain English — before you commit to anything.',
                       'cta' => 'Request a call', 'cta2' => 'How we work'],
            'about' => ['title' => 'No jargon, no surprise invoices',
                        'lead' => 'Fixed fees agreed in writing before any work starts.',
                        'body' => ['{name} was set up by people who had spent long enough in large firms to know what clients actually dislike about them: the jargon, the hourly clock, and the invoice nobody warned you about.',
                                   'We quote a fixed fee for defined work, agree it in writing, and stick to it unless you change the brief. You get one named adviser who handles your matter from start to finish, and you always know what stage it is at.'],
                        'points' => ['Fixed fees agreed in writing', 'One named adviser per matter', 'Replies within one working day']],
            'services' => ['title' => 'How we help', 'sub' => 'Defined pieces of work with a price attached.',
                'items' => [
                    ['title' => 'Business advisory', 'text' => 'Structure, contracts and compliance for companies that have outgrown doing it themselves.'],
                    ['title' => 'Accounts & tax', 'text' => 'Year-end accounts, returns and the planning conversation that should happen before the deadline, not after it.'],
                    ['title' => 'Contracts & agreements', 'text' => 'Drafting and reviewing the documents your business runs on, in language your team can actually follow.'],
                    ['title' => 'Dispute support', 'text' => 'Early, honest assessment of where you stand, and a realistic view of what fighting it would cost you.'],
                ]],
            'stats' => [['value' => '18yr', 'label' => 'Average partner experience'], ['value' => '1 day', 'label' => 'Response guarantee'], ['value' => '96%', 'label' => 'Clients who return']],
            'testimonials' => [
                ['quote' => 'They told me not to pursue a claim that would have cost more than it recovered. That is why we still use them.', 'name' => 'Owen Bradshaw', 'role' => 'Managing Director, Kestrel Ltd'],
                ['quote' => 'The first accountant who explained what the numbers meant instead of just filing them.', 'name' => 'Fiona Clarke', 'role' => 'Founder, Northgate Studio'],
            ],
            'faq' => [
                ['q' => 'How are your fees calculated?', 'a' => 'Fixed fee per piece of work, quoted in writing before we start. Where a matter genuinely cannot be scoped up front we agree a cap with you first.'],
                ['q' => 'Who will I be dealing with?', 'a' => 'One named adviser handles your matter throughout. You will have their direct line and email.'],
                ['q' => 'How quickly do you respond?', 'a' => 'Within one working day, every time. If your adviser is in court or on leave, someone briefed on your matter replies instead.'],
            ],
            'contact' => ['email' => 'hello@example.com', 'phone' => '+44 20 7946 0770', 'address' => '4 Bishopsgate Court, London EC2N 3AQ', 'hours' => 'Mon – Fri, 9am – 5.30pm'],
        ],

        'realestate' => [
            'match' => ['real estate', 'estate agent', 'property', 'letting', 'realtor', 'housing', 'rental', 'landlord', 'mortgage'],
            'name' => 'Halloway Property', 'tagline' => 'Local knowledge, honest valuations', 'palette' => 'forest', 'style' => 'modern',
            'sections' => ['hero', 'stats', 'about', 'services', 'team', 'testimonials', 'faq', 'cta', 'contact'],
            'hero' => ['eyebrow' => 'Sales, lettings & management', 'title' => 'Know what your property is really worth',
                       'sub' => '{name} values honestly, markets properly, and tells you when the offer on the table is the right one.',
                       'cta' => 'Book a valuation', 'cta2' => 'Our services'],
            'about' => ['title' => 'We would rather be right than optimistic',
                        'lead' => 'Overvaluing a property wins the instruction and loses the sale. We do not do it.',
                        'body' => ['{name} has worked these streets for eighteen years. That means we can tell you what a property is worth without guessing, and why the identical house two roads over sold for eleven thousand less.',
                                   'Every listing gets proper photography, a floor plan, and a written marketing plan before it goes live. We report back weekly whether there is good news or not.'],
                        'points' => ['Valuations based on comparable local sales', 'Professional photography and floor plans included', 'Weekly written updates, good news or bad']],
            'services' => ['title' => 'What we do', 'sub' => 'Selling, letting and looking after property.',
                'items' => [
                    ['title' => 'Sales', 'text' => 'Full marketing, accompanied viewings, and negotiation handled by the person who valued the property in the first place.'],
                    ['title' => 'Lettings', 'text' => 'Referenced tenants, compliant paperwork and a rent figure based on what is actually letting right now.'],
                    ['title' => 'Property management', 'text' => 'Inspections, repairs and rent collection handled end to end, with a vetted contractor list and no mark-up on invoices.'],
                    ['title' => 'Valuations', 'text' => 'A free, no-obligation valuation with the comparable sales that justify it, so you can see the reasoning.'],
                ]],
            'stats' => [['value' => '18yr', 'label' => 'In this area'], ['value' => '£0', 'label' => 'Mark-up on repairs'], ['value' => '24d', 'label' => 'Average time to offer']],
            'testimonials' => [
                ['quote' => 'Two other agents valued fifteen thousand higher. Halloway sold it in three weeks at their number.', 'name' => 'Peter and Anne Doyle', 'role' => 'Sold in 2025'],
                ['quote' => 'They have managed my flat for four years and I have never had to chase anything.', 'name' => 'Yusuf Karim', 'role' => 'Landlord'],
            ],
            'faq' => [
                ['q' => 'What do you charge?', 'a' => 'A percentage on sales, agreed before instruction, with no upfront marketing costs. Management is a flat monthly percentage with no mark-up on contractor invoices.'],
                ['q' => 'How long are you tied in for?', 'a' => 'Twelve weeks on a sole agency agreement. If you are unhappy before then, tell us and we will release you.'],
                ['q' => 'Do you do accompanied viewings?', 'a' => 'Always, including evenings and weekends. Nobody walks around your home unaccompanied.'],
            ],
            'contact' => ['email' => 'hello@example.com', 'phone' => '+44 131 496 0620', 'address' => '17 Halloway Road, Edinburgh EH3 9QG', 'hours' => 'Mon – Fri 9am – 6pm, Sat 10am – 4pm'],
        ],

        'photography' => [
            'match' => ['photograph', 'photo studio', 'videograph', 'film maker', 'filmmaker', 'wedding photo', 'portrait'],
            'name' => 'Field & Frame', 'tagline' => 'Photographs that still feel like the day', 'palette' => 'slate', 'style' => 'minimal',
            'sections' => ['hero', 'gallery', 'about', 'services', 'pricing', 'testimonials', 'faq', 'cta', 'contact'],
            'hero' => ['eyebrow' => 'Weddings, portraits & editorial', 'title' => 'The day as it actually felt',
                       'sub' => '{name} works quietly and shoots honestly — no stiff line-ups, no three hours lost to formal photographs.',
                       'cta' => 'Check availability', 'cta2' => 'See the work'],
            'about' => ['title' => 'Documentary first, directed only when it helps',
                        'lead' => 'I photograph what is happening, and step in only when a picture genuinely needs it.',
                        'body' => ['{name} shoots in a documentary way: most of the day is photographed as it unfolds, without interruption. Formal groups are done properly but quickly — twenty minutes, a written list agreed in advance, and then back to the day.',
                                   'Every gallery is hand-edited rather than run through a preset. You get the full set of finished images, high resolution, with a licence to print whatever you like.'],
                        'points' => ['Formal groups done in twenty minutes', 'Every image hand-edited', 'Full-resolution files with a print licence']],
            'services' => ['title' => 'What I photograph', 'sub' => 'Booked directly with me, start to finish.',
                'items' => [
                    ['title' => 'Weddings', 'text' => 'Full-day coverage from preparations to the first dances, delivered as a hand-edited gallery within four weeks.'],
                    ['title' => 'Portraits', 'text' => 'Relaxed sessions on location — families, couples and individuals who dislike being photographed.'],
                    ['title' => 'Editorial & brand', 'text' => 'Photography for magazines, brands and small businesses that need images which look like them, not like stock.'],
                    ['title' => 'Film', 'text' => 'A short documentary edit alongside the stills, cut from audio recorded on the day rather than a licensed track.'],
                ]],
            'stats' => [['value' => '4wk', 'label' => 'Gallery turnaround'], ['value' => '600+', 'label' => 'Images per wedding'], ['value' => '11yr', 'label' => 'Shooting professionally']],
            'testimonials' => [
                ['quote' => 'We barely noticed the camera all day, then got four hundred photographs of things we did not know happened.', 'name' => 'Ciara and Tom Hughes', 'role' => 'Married 2025'],
                ['quote' => 'Turned around a brand shoot in a week and half the shots are still on our homepage.', 'name' => 'Lena Fischer', 'role' => 'Marketing Lead, Wren Studio'],
            ],
            'pricing' => [
                ['name' => 'Portrait', 'price' => '£340', 'note' => '90 minutes', 'features' => ['On-location session', '40+ edited images', 'Online gallery', 'Full print licence'], 'featured' => false],
                ['name' => 'Wedding', 'price' => '£1,850', 'note' => 'full day', 'features' => ['Ten hours of coverage', '600+ edited images', 'Pre-wedding planning call', 'Gallery within four weeks'], 'featured' => true],
                ['name' => 'Wedding + film', 'price' => '£2,900', 'note' => 'full day', 'features' => ['Everything in Wedding', 'Six-minute documentary edit', 'Audio recorded on the day', 'Second photographer'], 'featured' => false],
            ],
            'faq' => [
                ['q' => 'How far will you travel?', 'a' => 'Anywhere in the UK at no extra cost. Overseas is possible — travel and one night\'s accommodation added at cost.'],
                ['q' => 'How long until we see the photographs?', 'a' => 'A preview set within 48 hours and the full hand-edited gallery inside four weeks.'],
                ['q' => 'Do we have to do formal group shots?', 'a' => 'Only if you want them. If you do, we agree the list in advance and get through it in twenty minutes.'],
            ],
            'contact' => ['email' => 'hello@example.com', 'phone' => '+44 7700 900184', 'address' => 'Studio 6, The Maltings, York YO1 9TL', 'hours' => 'Mon – Fri, 10am – 6pm'],
        ],

        'portfolio' => [
            'match' => ['portfolio', 'my personal', 'personal site', 'resume', 'cv', 'freelance', 'designer', 'developer', 'illustrator', 'writer', 'about me'],
            'name' => 'Sam Okonkwo', 'tagline' => 'Designer and front-end developer', 'palette' => 'violet', 'style' => 'minimal',
            'sections' => ['hero', 'about', 'services', 'gallery', 'stats', 'testimonials', 'cta', 'contact'],
            'hero' => ['eyebrow' => 'Available for freelance work', 'title' => 'I design and build things for the web',
                       'sub' => 'I am {name}. I work with small teams on interfaces that are quick to use, easy to maintain and pleasant to look at.',
                       'cta' => 'Get in touch', 'cta2' => 'See selected work'],
            'about' => ['title' => 'About me',
                        'lead' => 'Nine years of design work, six of them writing the front-end code as well.',
                        'body' => ['I am {name}, a designer who codes. I have spent nine years working on product interfaces, mostly for teams small enough that the person designing a screen is also the person shipping it.',
                                   'That combination means fewer handovers and fewer arguments about what is technically possible. I work directly with founders and product leads, usually in short focused engagements rather than open-ended retainers.'],
                        'points' => ['Design and front-end in one pair of hands', 'Comfortable in Figma and in a codebase', 'Short, defined engagements']],
            'services' => ['title' => 'What I do', 'sub' => 'Three things, done properly.',
                'items' => [
                    ['title' => 'Product design', 'text' => 'Interface design for web apps — flows, screens and the design system that keeps them consistent as the team grows.'],
                    ['title' => 'Front-end build', 'text' => 'Accessible, responsive front-end code. Semantic markup, sensible CSS, and JavaScript only where it earns its place.'],
                    ['title' => 'Design systems', 'text' => 'Component libraries and documentation that survive contact with a real team and a real deadline.'],
                    ['title' => 'Consulting', 'text' => 'A few days of focused review when you need a second opinion rather than a full engagement.'],
                ]],
            'stats' => [['value' => '9yr', 'label' => 'Designing'], ['value' => '40+', 'label' => 'Projects shipped'], ['value' => '6', 'label' => 'Design systems built']],
            'testimonials' => [
                ['quote' => 'Delivered a design system in five weeks that our engineers still use two years later.', 'name' => 'Ines Duarte', 'role' => 'Head of Product, Cadence'],
                ['quote' => 'Rare to find someone who can design the thing and then actually build it well.', 'name' => 'Marcus Lin', 'role' => 'CTO, Northbay'],
            ],
            'contact' => ['email' => 'hello@example.com', 'phone' => '+44 7700 900233', 'address' => 'Remote — UK based', 'hours' => 'Mon – Thu, 9am – 5pm'],
        ],

        'agency' => [
            'match' => ['agency', 'marketing', 'branding', 'creative studio', 'digital studio', 'seo', 'advertis', 'design studio', 'web design'],
            'name' => 'North & Signal', 'tagline' => 'Brand and digital work that pays for itself', 'palette' => 'indigo', 'style' => 'bold',
            'sections' => ['hero', 'stats', 'about', 'services', 'gallery', 'testimonials', 'pricing', 'cta', 'contact'],
            'hero' => ['eyebrow' => 'Brand · Web · Growth', 'title' => 'Work that moves the number you care about',
                       'sub' => '{name} is a small studio that takes on a few projects at a time, and measures them against your commercial goals rather than an award entry.',
                       'cta' => 'Start a project', 'cta2' => 'See our work'],
            'about' => ['title' => 'Small studio, senior people',
                        'lead' => 'The people you meet in the pitch are the people who do the work.',
                        'body' => ['{name} is deliberately small. We take on a handful of projects at a time so that the senior people you meet at the start are still the ones doing the work at the end — no handing your account to a junior once the contract is signed.',
                                   'Every engagement starts with what success actually looks like in numbers. We agree how it will be measured before we start designing anything, and we report against it honestly.'],
                        'points' => ['Senior team, no account handoffs', 'Success metrics agreed before work starts', 'Fixed scope, fixed price']],
            'services' => ['title' => 'What we do', 'sub' => 'Brand, web and the growth work that follows.',
                'items' => [
                    ['title' => 'Brand identity', 'text' => 'Positioning, naming, identity systems and the guidelines that stop it falling apart six months later.'],
                    ['title' => 'Websites', 'text' => 'Fast, accessible sites designed around conversion, built to be edited by your team without calling us.'],
                    ['title' => 'Campaigns', 'text' => 'Integrated campaigns with a measurement plan attached, so you know which half of the budget worked.'],
                    ['title' => 'Growth & SEO', 'text' => 'Technical SEO, content strategy and conversion work — the unglamorous things that compound.'],
                ]],
            'stats' => [['value' => '4', 'label' => 'Projects at a time'], ['value' => '2.8x', 'label' => 'Median conversion lift'], ['value' => '12yr', 'label' => 'Average team experience']],
            'testimonials' => [
                ['quote' => 'Rebrand and new site together lifted qualified enquiries by 140% in one quarter.', 'name' => 'Alice Trent', 'role' => 'CEO, Verdant Group'],
                ['quote' => 'They pushed back on half our brief and the project was much better for it.', 'name' => 'Rob Ellery', 'role' => 'Marketing Director, Halcyon'],
            ],
            'pricing' => [
                ['name' => 'Sprint', 'price' => '£4,500', 'note' => 'two weeks', 'features' => ['Focused two-week engagement', 'One defined deliverable', 'Senior team throughout', 'Handover documentation'], 'featured' => false],
                ['name' => 'Project', 'price' => '£18,000', 'note' => 'typical', 'features' => ['Brand or website end to end', 'Research and strategy', 'Full design and build', 'Three months of support'], 'featured' => true],
                ['name' => 'Partner', 'price' => 'Custom', 'note' => 'retained', 'features' => ['Ongoing monthly capacity', 'Quarterly strategy sessions', 'Priority scheduling', 'Shared reporting dashboard'], 'featured' => false],
            ],
            'contact' => ['email' => 'hello@example.com', 'phone' => '+44 161 496 0777', 'address' => '2nd Floor, Signal House, Manchester M2 4WU', 'hours' => 'Mon – Fri, 9am – 6pm'],
        ],

        'saas' => [
            'match' => ['saas', 'software', 'app', 'startup', 'platform', 'dashboard', 'tool for', 'api', 'automation', 'crm', 'tech company'],
            'name' => 'Cadence', 'tagline' => 'The operations layer your team stops fighting', 'palette' => 'indigo', 'style' => 'modern',
            'sections' => ['hero', 'stats', 'services', 'about', 'pricing', 'testimonials', 'faq', 'cta', 'contact'],
            'hero' => ['eyebrow' => 'Free 14-day trial · No card required', 'title' => 'Stop running your team out of a spreadsheet',
                       'sub' => '{name} pulls your projects, deadlines and approvals into one place — and tells you what is about to slip before it does.',
                       'cta' => 'Start free trial', 'cta2' => 'See how it works'],
            'about' => ['title' => 'Built for teams that outgrew the spreadsheet',
                        'lead' => 'Set up in an afternoon. Useful on day one.',
                        'body' => ['{name} came out of a consultancy that kept rebuilding the same tracking spreadsheet for every client. Eventually we built the tool properly and gave it to everyone else.',
                                   'It is deliberately not a general-purpose project tool. It does scheduling, approvals and status reporting, and it does them without a two-week onboarding programme. Most teams are running on it the same afternoon they sign up.'],
                        'points' => ['Set up in an afternoon, not a quarter', 'Imports directly from your existing sheet', 'SSO, audit logs and data export on every plan']],
            'services' => ['title' => 'What it does', 'sub' => 'Four things, and no feature you will never open.',
                'items' => [
                    ['title' => 'Live scheduling', 'text' => 'Every project on one timeline, with automatic flags the moment a dependency slips.'],
                    ['title' => 'Approvals', 'text' => 'Structured sign-off with a full audit trail, so nobody has to search their inbox for who agreed what.'],
                    ['title' => 'Reporting', 'text' => 'Status reports generated from live data instead of assembled by hand every Friday afternoon.'],
                    ['title' => 'Integrations', 'text' => 'Two-way sync with the calendar, chat and storage tools your team already has open.'],
                ]],
            'stats' => [['value' => '2,400+', 'label' => 'Teams onboard'], ['value' => '99.98%', 'label' => 'Uptime, last 12 months'], ['value' => '<1hr', 'label' => 'Median support reply']],
            'testimonials' => [
                ['quote' => 'Replaced four spreadsheets and a weekly status meeting. Nobody has asked to go back.', 'name' => 'Dana Whitmore', 'role' => 'COO, Brightpath'],
                ['quote' => 'Onboarded thirty people in a day without a single support ticket.', 'name' => 'Kwame Boateng', 'role' => 'Head of Delivery, Ardent'],
            ],
            'pricing' => [
                ['name' => 'Starter', 'price' => '$0', 'note' => 'up to 3 users', 'features' => ['Unlimited projects', 'Live scheduling', 'Community support', 'Data export'], 'featured' => false],
                ['name' => 'Team', 'price' => '$14', 'note' => 'per user / month', 'features' => ['Everything in Starter', 'Approvals and audit trail', 'Automated reporting', 'Integrations', 'Email and chat support'], 'featured' => true],
                ['name' => 'Enterprise', 'price' => 'Custom', 'note' => 'annual', 'features' => ['Everything in Team', 'SSO and SCIM', 'Dedicated success manager', '99.9% uptime SLA', 'Security review support'], 'featured' => false],
            ],
            'faq' => [
                ['q' => 'Do I need a credit card to try it?', 'a' => 'No. The fourteen-day trial needs an email address and nothing else, and it does not expire into a charge.'],
                ['q' => 'Can we import our existing spreadsheet?', 'a' => 'Yes — paste it in or upload a CSV and the importer maps the columns for you. Most teams are migrated in under an hour.'],
                ['q' => 'Where is our data stored?', 'a' => 'In the EU or the US, whichever you pick at signup. You can export everything as CSV or JSON at any time, on any plan.'],
            ],
            'contact' => ['email' => 'hello@example.com', 'phone' => '+44 20 7946 0999', 'address' => '5 Herald Square, London EC1V 9BD', 'hours' => 'Support Mon – Fri, 8am – 8pm'],
        ],

        'shop' => [
            'match' => ['shop', 'store', 'ecommerce', 'e-commerce', 'boutique', 'retail', 'products', 'merch', 'sell online', 'clothing', 'jewellery'],
            'name' => 'Thornby Goods', 'tagline' => 'Things made well, meant to last', 'palette' => 'sand', 'style' => 'elegant',
            'sections' => ['hero', 'services', 'gallery', 'about', 'stats', 'testimonials', 'faq', 'cta', 'contact'],
            'hero' => ['eyebrow' => 'Free UK delivery over £60', 'title' => 'Fewer things, chosen properly',
                       'sub' => '{name} stocks a small, considered range from makers we have actually met — and will happily tell you which of two options to buy.',
                       'cta' => 'Shop the range', 'cta2' => 'Our story'],
            'about' => ['title' => 'A shop with an opinion',
                        'lead' => 'We stock roughly two hundred products, and we can tell you where each one came from.',
                        'body' => ['{name} carries a deliberately small range. Everything is chosen by us, used by us, and bought from makers we can name. If we would not own it ourselves, it is not on the shelf.',
                                   'That means we can answer questions properly. Ask which of two kettles to buy and you will get an actual recommendation rather than a link to the reviews.'],
                        'points' => ['Around 200 products, all hand-picked', 'Free UK delivery over £60', '60-day returns, no questions']],
            'services' => ['title' => 'What we stock', 'sub' => 'Four small ranges, restocked as they sell.',
                'items' => [
                    ['title' => 'Kitchen & table', 'text' => 'Cookware, ceramics and tools built to be used daily and repaired rather than replaced.'],
                    ['title' => 'Home & textiles', 'text' => 'Linen, wool and cotton pieces from small European mills, in colours that do not date.'],
                    ['title' => 'Stationery', 'text' => 'Notebooks, pens and paper goods for people who still write things down on purpose.'],
                    ['title' => 'Gifts', 'text' => 'A curated shelf under £40, wrapped by hand and posted with a handwritten card if you ask.'],
                ]],
            'stats' => [['value' => '200', 'label' => 'Products, not thousands'], ['value' => '60d', 'label' => 'Returns window'], ['value' => '48hr', 'label' => 'Typical dispatch']],
            'testimonials' => [
                ['quote' => 'Emailed asking which of two pans to buy and got a three-paragraph answer from an actual human.', 'name' => 'Grace Lambert', 'role' => 'Customer'],
                ['quote' => 'Everything I have bought here I still own. That is not true of anywhere else I shop.', 'name' => 'Michael Serrano', 'role' => 'Customer since 2020'],
            ],
            'faq' => [
                ['q' => 'How fast is delivery?', 'a' => 'Orders placed before 2pm on a working day are dispatched the same day, and usually arrive within 48 hours. Free over £60.'],
                ['q' => 'What is your returns policy?', 'a' => 'Sixty days, unused, in the original packaging. We pay return postage on anything faulty.'],
                ['q' => 'Do you ship internationally?', 'a' => 'Across the EU and to the US. Duties are calculated at checkout so there is no surprise bill on delivery.'],
            ],
            'contact' => ['email' => 'hello@example.com', 'phone' => '+44 1225 496011', 'address' => '11 Thornby Row, Bath BA1 5NL', 'hours' => 'Shop Tue – Sat, 10am – 6pm'],
        ],

        'education' => [
            'match' => ['school', 'academy', 'college', 'course', 'tutor', 'training', 'education', 'learn', 'nursery', 'university', 'workshop'],
            'name' => 'Redgrove Academy', 'tagline' => 'Small classes, taught by specialists', 'palette' => 'ocean', 'style' => 'modern',
            'sections' => ['hero', 'stats', 'about', 'services', 'team', 'testimonials', 'faq', 'cta', 'contact'],
            'hero' => ['eyebrow' => 'Enrolling for September', 'title' => 'Teaching that keeps up with the student',
                       'sub' => '{name} keeps classes small enough that every teacher knows exactly where each student is and what they need next.',
                       'cta' => 'Book a visit', 'cta2' => 'Our courses'],
            'about' => ['title' => 'Twelve students to a class',
                        'lead' => 'Small enough that nobody drifts through a term unnoticed.',
                        'body' => ['{name} caps classes at twelve. It is the single decision that shapes everything else: teachers know where every student is, feedback arrives while the work is still fresh, and nobody spends a term quietly falling behind.',
                                   'Every subject is taught by a specialist in that subject, and progress is reported to parents every half term in plain language rather than a grid of grades.'],
                        'points' => ['Maximum twelve students per class', 'Every subject taught by a specialist', 'Plain-language reports every half term']],
            'services' => ['title' => 'What we teach', 'sub' => 'Structured courses with real assessment.',
                'items' => [
                    ['title' => 'Core curriculum', 'text' => 'Full subject teaching to national standards, with the depth that a class of twelve allows.'],
                    ['title' => 'Exam preparation', 'text' => 'Focused revision courses with past-paper practice and marking against the actual mark scheme.'],
                    ['title' => 'One-to-one tutoring', 'text' => 'Individual sessions for students who need to catch up in one subject or push much further ahead in it.'],
                    ['title' => 'Holiday workshops', 'text' => 'Short intensive courses during school holidays, from coding to essay technique.'],
                ]],
            'stats' => [['value' => '12', 'label' => 'Students per class'], ['value' => '94%', 'label' => 'Grades 7–9 at GCSE'], ['value' => '2005', 'label' => 'Teaching since']],
            'testimonials' => [
                ['quote' => 'Our daughter went from a grade 4 to a grade 8 in one year, and actually enjoys the subject now.', 'name' => 'Helen Voss', 'role' => 'Parent'],
                ['quote' => 'The half-term reports actually say something. I know exactly what to help with at home.', 'name' => 'Ade Fasanya', 'role' => 'Parent'],
            ],
            'faq' => [
                ['q' => 'When can we visit?', 'a' => 'We run open mornings monthly and are happy to arrange individual visits during any teaching week — seeing a normal lesson tells you more than an open day.'],
                ['q' => 'What are the fees?', 'a' => 'Published in full on request, with no compulsory extras. Means-tested bursaries cover up to 100% of fees for eligible families.'],
                ['q' => 'How do you report progress?', 'a' => 'A written report every half term in plain English, plus a parents\' evening each term and a direct email address for every subject teacher.'],
            ],
            'contact' => ['email' => 'hello@example.com', 'phone' => '+44 1865 496022', 'address' => 'Redgrove House, Oxford OX2 6PL', 'hours' => 'Office Mon – Fri, 8am – 5pm'],
        ],

        'nonprofit' => [
            'match' => ['charity', 'nonprofit', 'non-profit', 'ngo', 'foundation', 'community', 'volunteer', 'donation', 'association', 'fundrais'],
            'name' => 'The Wayfield Trust', 'tagline' => 'Practical help, close to home', 'palette' => 'emerald', 'style' => 'modern',
            'sections' => ['hero', 'stats', 'about', 'services', 'testimonials', 'team', 'faq', 'cta', 'contact'],
            'hero' => ['eyebrow' => 'Registered charity no. 1148802', 'title' => 'Small charity. Measurable difference.',
                       'sub' => '{name} funds practical support in this borough and publishes exactly where every pound goes.',
                       'cta' => 'Donate', 'cta2' => 'How we spend it'],
            'about' => ['title' => 'Where the money goes',
                        'lead' => '88p in every pound reaches the programmes. We publish the rest.',
                        'body' => ['{name} works in one borough rather than spreading itself thin across the country. That means we know the organisations we fund, we visit them, and we can say what changed as a result.',
                                   'Our accounts are published in full every year alongside a plain-English impact report. Eighty-eight pence in every pound goes directly to programmes; the remainder covers the staff and audit costs that keep us accountable.'],
                        'points' => ['88p of every pound reaches programmes', 'Full accounts published annually', 'Every funded partner visited in person']],
            'services' => ['title' => 'What we fund', 'sub' => 'Four programmes, reviewed every year.',
                'items' => [
                    ['title' => 'Food and essentials', 'text' => 'Direct funding for local food banks and emergency grants for families in crisis.'],
                    ['title' => 'Youth programmes', 'text' => 'After-school provision and holiday clubs for young people who would otherwise have nowhere to go.'],
                    ['title' => 'Skills and employment', 'text' => 'Training, interview support and travel grants for people getting back into work.'],
                    ['title' => 'Small grants', 'text' => 'Grants up to £2,000 for community groups, decided in four weeks with a one-page application.'],
                ]],
            'stats' => [['value' => '88p', 'label' => 'Of each £1 to programmes'], ['value' => '2,600', 'label' => 'People supported last year'], ['value' => '31', 'label' => 'Partner organisations']],
            'testimonials' => [
                ['quote' => 'The grant covered our holiday club for a whole summer. Forty families used it.', 'name' => 'Bev Ncube', 'role' => 'Coordinator, Elmside Community Hub'],
                ['quote' => 'One page to apply and a decision in three weeks. Unheard of.', 'name' => 'Ryan Doherty', 'role' => 'Trustee, Northside Youth'],
            ],
            'faq' => [
                ['q' => 'How much of my donation reaches the programmes?', 'a' => '88p in every pound. The remaining 12p covers staff, audit and governance, and our full accounts are published each year.'],
                ['q' => 'Can I volunteer?', 'a' => 'Yes. We need help with events, admin and partner visits. Get in touch and we will match you to something that fits your time.'],
                ['q' => 'How do we apply for a grant?', 'a' => 'A one-page form for grants up to £2,000, with a decision within four weeks. Larger grants have a longer process; ask us first.'],
            ],
            'contact' => ['email' => 'hello@example.com', 'phone' => '+44 20 7946 0512', 'address' => 'Wayfield House, 60 Bell Street, London SE1 2QT', 'hours' => 'Mon – Thu, 9.30am – 4.30pm'],
        ],

        'construction' => [
            'match' => ['builder', 'construction', 'contractor', 'renovation', 'roofing', 'plumb', 'electric', 'joinery', 'landscap', 'interior design', 'architect', 'carpentry'],
            'name' => 'Halden Build', 'tagline' => 'Finished on time, priced honestly', 'palette' => 'gold', 'style' => 'bold',
            'sections' => ['hero', 'stats', 'services', 'about', 'gallery', 'testimonials', 'faq', 'cta', 'contact'],
            'hero' => ['eyebrow' => 'Fully insured · 10-year guarantee', 'title' => 'A quote that holds, and a date that sticks',
                       'sub' => '{name} gives you a fixed written quote, a start date we keep, and one contact who answers the phone for the whole job.',
                       'cta' => 'Get a quote', 'cta2' => 'See recent work'],
            'about' => ['title' => 'The boring things done right',
                        'lead' => 'Fixed quotes, kept dates, and a site left tidy every evening.',
                        'body' => ['{name} has been building and renovating across the region for over twenty years. Most of our work comes from people whose neighbours used us, which tells you more than any advert would.',
                                   'You get a fixed written quote with a full breakdown, a start date we commit to, and one project lead who is on site and reachable throughout. Variations are priced and agreed in writing before anyone picks up a tool.'],
                        'points' => ['Fixed written quotes with full breakdowns', 'Variations agreed in writing first', 'Ten-year workmanship guarantee']],
            'services' => ['title' => 'What we build', 'sub' => 'Domestic and light commercial work.',
                'items' => [
                    ['title' => 'Extensions', 'text' => 'Single and double-storey extensions handled end to end, including drawings, building control and party wall notices.'],
                    ['title' => 'Renovations', 'text' => 'Full-house refurbishment — rewiring, replumbing, plastering and joinery, sequenced so the trades are not tripping over each other.'],
                    ['title' => 'Kitchens & bathrooms', 'text' => 'Complete fit-outs with all trades in-house, typically finished within three weeks of starting.'],
                    ['title' => 'Loft conversions', 'text' => 'Structural work, staircase, insulation and finish, delivered to building regulations with certificates in hand.'],
                ]],
            'stats' => [['value' => '20yr', 'label' => 'In business'], ['value' => '10yr', 'label' => 'Workmanship guarantee'], ['value' => '£5m', 'label' => 'Public liability cover']],
            'testimonials' => [
                ['quote' => 'Quoted in March, started the week they said, finished two days early. The quote never moved.', 'name' => 'Sarah Whitcombe', 'role' => 'Double extension, 2025'],
                ['quote' => 'Site was swept every evening. My neighbours commented on it, which says everything.', 'name' => 'Andrew Pike', 'role' => 'Loft conversion'],
            ],
            'faq' => [
                ['q' => 'How accurate is the quote?', 'a' => 'It is fixed. The only things that change it are variations you ask for, and those are priced and signed off in writing before we proceed.'],
                ['q' => 'Do you handle planning and building control?', 'a' => 'Yes — drawings, applications, building control sign-off and party wall notices are all included on extension and loft work.'],
                ['q' => 'Are you insured?', 'a' => 'Fully. £5m public liability, employer\'s liability, and a ten-year workmanship guarantee in writing on completion.'],
            ],
            'contact' => ['email' => 'hello@example.com', 'phone' => '+44 1332 496033', 'address' => 'Halden Yard, Derby DE1 3QN', 'hours' => 'Mon – Fri 7.30am – 5pm, Sat 8am – 12pm'],
        ],

        'travel' => [
            'match' => ['hotel', 'travel', 'tour', 'holiday', 'bnb', 'b&b', 'guest house', 'resort', 'lodge', 'retreat', 'hostel', 'campsite'],
            'name' => 'Glenmore Lodge', 'tagline' => 'A quiet place to stop for a while', 'palette' => 'forest', 'style' => 'elegant',
            'sections' => ['hero', 'about', 'services', 'gallery', 'stats', 'testimonials', 'faq', 'cta', 'contact'],
            'hero' => ['eyebrow' => 'Nine rooms · Two hours from the city', 'title' => 'Somewhere worth the drive',
                       'sub' => '{name} has nine rooms, a long kitchen table and no televisions. People tend to stay a night longer than they planned.',
                       'cta' => 'Check availability', 'cta2' => 'See the rooms'],
            'about' => ['title' => 'Nine rooms, one table',
                        'lead' => 'Small enough that we cook for everyone staying, most nights.',
                        'body' => ['{name} is a converted farmhouse with nine rooms, run by the two of us. Breakfast is cooked to order, dinner is served at one long table, and the kettle is always on in the hall.',
                                   'There are no televisions in the rooms. There is a fire, a shelf of books, a drying room for wet boots, and a valley outside that is worth getting up early for.'],
                        'points' => ['Nine rooms, no two the same', 'Breakfast cooked to order', 'Dogs welcome in four rooms']],
            'services' => ['title' => 'Staying here', 'sub' => 'What is included, and what you can add.',
                'items' => [
                    ['title' => 'Rooms', 'text' => 'Nine individual rooms, all en-suite, most looking down the valley. Four of them take dogs at no extra charge.'],
                    ['title' => 'Dinner', 'text' => 'A set three-course dinner at the long table most evenings, cooked from whatever the local growers had that week.'],
                    ['title' => 'Walking', 'text' => 'Routes from the door for every ability, packed lunches on request, and a drying room for when it does what it usually does.'],
                    ['title' => 'Whole-house hire', 'text' => 'Take all nine rooms for a family gathering or a small retreat, with the kitchen and dining room to yourselves.'],
                ]],
            'stats' => [['value' => '9', 'label' => 'Rooms'], ['value' => '4', 'label' => 'Dog-friendly rooms'], ['value' => '2.4', 'label' => 'Average nights stayed']],
            'testimonials' => [
                ['quote' => 'Booked two nights and stayed four. The dinners alone are worth the drive.', 'name' => 'Louise Kerr', 'role' => 'Guest, autumn 2025'],
                ['quote' => 'No TV in the room turned out to be the best thing about it.', 'name' => 'Patrick Sinclair', 'role' => 'Guest'],
            ],
            'faq' => [
                ['q' => 'Is dinner included?', 'a' => 'Breakfast is included; dinner is £38 per person and booked when you arrive, so the kitchen knows numbers.'],
                ['q' => 'Can we bring the dog?', 'a' => 'Four of the nine rooms take dogs at no extra charge. Mention it when you book so we allocate the right room.'],
                ['q' => 'What is the cancellation policy?', 'a' => 'Free cancellation up to seven days before arrival. Inside seven days we charge the first night unless we can re-let the room.'],
            ],
            'contact' => ['email' => 'hello@example.com', 'phone' => '+44 1479 496044', 'address' => 'Glenmore, Aviemore PH22 1QU', 'hours' => 'Reception 8am – 9pm daily'],
        ],

        'generic' => [
            'match' => [],
            'name' => 'Northbridge', 'tagline' => 'Careful work for people who notice the difference', 'palette' => 'indigo', 'style' => 'modern',
            'sections' => ['hero', 'stats', 'about', 'services', 'testimonials', 'faq', 'cta', 'contact'],
            'hero' => ['eyebrow' => 'Trusted since 2012', 'title' => 'Done properly, first time',
                       'sub' => '{name} takes on work we can do well, quotes it honestly, and finishes it when we said we would.',
                       'cta' => 'Get in touch', 'cta2' => 'What we do'],
            'about' => ['title' => 'About us',
                        'lead' => 'A small team, a clear process, and no surprises on the invoice.',
                        'body' => ['{name} has been doing this since 2012. We have stayed deliberately small so that the person you speak to first is involved in the work all the way through, and so we can turn down anything we would not do well.',
                                   'Every job starts with a proper conversation about what you actually need, followed by a written quote and an agreed timeline. If something changes along the way, you hear about it from us before it becomes a problem.'],
                        'points' => ['Written quotes, agreed before work starts', 'One point of contact throughout', 'Most work delivered inside two weeks']],
            'services' => ['title' => 'What we do', 'sub' => 'Four things, done thoroughly.',
                'items' => [
                    ['title' => 'Consultation', 'text' => 'A proper conversation about what you need, what it will cost, and whether we are the right people for it.'],
                    ['title' => 'Delivery', 'text' => 'The work itself, done to an agreed scope and timeline by the people who scoped it.'],
                    ['title' => 'Ongoing support', 'text' => 'We stay reachable after the job is finished. Questions six months later still get answered.'],
                    ['title' => 'Reviews', 'text' => 'A scheduled check-in to see whether what we delivered is still doing its job, and to fix it if not.'],
                ]],
            'stats' => [['value' => '2012', 'label' => 'Established'], ['value' => '500+', 'label' => 'Jobs completed'], ['value' => '4.9', 'label' => 'Average rating']],
            'testimonials' => [
                ['quote' => 'Straightforward from the first phone call to the final invoice. Would use again without hesitating.', 'name' => 'Claire Bennett', 'role' => 'Client'],
                ['quote' => 'They told me what I did not need, which is how I knew to trust the rest of it.', 'name' => 'Sam Iqbal', 'role' => 'Client'],
            ],
        ],

        ];
    }
}
