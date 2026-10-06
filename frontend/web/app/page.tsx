import ArrowForward from "@mui/icons-material/ArrowForward";
import Button from "@mui/material/Button";
import Container from "@mui/material/Container";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";

export default function Home() {
  return (
    <Container component="main" maxWidth="lg" sx={{ py: { xs: 7, md: 10 } }}>
      <Stack spacing={6} sx={{ maxWidth: "var(--content-width-lead)" }}>
        <Typography component="p" variant="overline" color="text.secondary">
          SL Furnitures
        </Typography>
        <Stack spacing={3}>
          <Typography component="h1" variant="h1">
            Furniture with a sense of place.
          </Typography>
          <Typography variant="body1" color="text.secondary">
            A considered collection of made-to-order furniture for spaces that
            feel lived in, useful, and entirely your own.
          </Typography>
        </Stack>
        <Button
          variant="contained"
          endIcon={<ArrowForward aria-hidden="true" />}
          sx={{ alignSelf: "flex-start" }}
        >
          Explore the collection
        </Button>
      </Stack>
    </Container>
  );
}
