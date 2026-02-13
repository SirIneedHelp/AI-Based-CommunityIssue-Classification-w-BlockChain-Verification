import { readFileSync } from "fs";
import path from "path";
import { fileURLToPath } from "url";
import { ethers } from "ethers";

// =====================
// Config
// =====================
const RPC_URL = "http://127.0.0.1:7545";

// If PRIVATE_KEY is not passed via environment, it will use this fallback.
// For production, ALWAYS pass PRIVATE_KEY from server environment.
const DEFAULT_PRIVATE_KEY =
  "0x99d8f2202e03882bb4387fd8fbcfa8916e9f7d9bd9f4f2f37483d6bc90f482cd";

const PRIVATE_KEY = process.env.PRIVATE_KEY || DEFAULT_PRIVATE_KEY;

// ✅ Put your deployed contract address here
// Tip: keep it updated after every redeploy
const CONTRACT_ADDRESS = "0x6744FE8C1c33472B1597FF0b32C752059dc11938";

// Resolve artifact path relative to this file (IMPORTANT for PHP calls)
const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const artifactPath = path.join(
  __dirname,
  "artifacts",
  "contracts",
  "Verification.sol",
  "Verification.json"
);

// =====================
// Helpers
// =====================
function usage() {
  console.log(
    "Usage:\n" +
      "  node verify.mjs <reportId> <dataHashBytes32> <category> <modelVersion>\n\n" +
      "Example:\n" +
      `  node verify.mjs 1 0x${"a".repeat(64)} "Garbage" "v1"\n\n` +
      "Notes:\n" +
      "- dataHashBytes32 must be 0x + 64 hex chars\n" +
      "- To test non-owner, pass PRIVATE_KEY:\n" +
      '  PRIVATE_KEY="0x..." node verify.mjs ...\n'
  );
}

function isBytes32(hex) {
  return /^0x[0-9a-fA-F]{64}$/.test(hex);
}

// =====================
// Main
// =====================
async function main() {
  const args = process.argv.slice(2);
  if (args.length < 4) {
    usage();
    process.exit(1);
  }

  const reportId = Number(args[0]);
  const dataHashHex = args[1];
  const category = args[2];
  const modelVersion = args[3];

  if (!Number.isInteger(reportId) || reportId < 0) {
    console.error("❌ reportId must be a non-negative integer");
    process.exit(1);
  }

  if (!isBytes32(dataHashHex)) {
  console.error("❌ dataHashBytes32 must be 0x + 64 hex chars (bytes32)");
  console.error("   received:", dataHashHex);
  process.exit(1);
}

// ✅ Force-cast to bytes32 (ethers v6 safe)
const dataHashBytes32 = ethers.hexlify(ethers.getBytes(dataHashHex));


  if (!category || category.trim().length === 0) {
    console.error("❌ category is required");
    process.exit(1);
  }

  if (!CONTRACT_ADDRESS || !ethers.isAddress(CONTRACT_ADDRESS)) {
    console.error("❌ CONTRACT_ADDRESS is missing or invalid in verify.mjs");
    process.exit(1);
  }

  // Load ABI safely (works even when called from PHP/XAMPP)
  const artifactRaw = readFileSync(artifactPath, "utf8");
  const artifact = JSON.parse(artifactRaw);
  const abi = artifact.abi;

  const provider = new ethers.JsonRpcProvider(RPC_URL);
  const wallet = new ethers.Wallet(PRIVATE_KEY, provider);

  const contract = new ethers.Contract(CONTRACT_ADDRESS, abi, wallet);

  const caller = await wallet.getAddress();
  const owner = await contract.owner();

  console.log("Caller address:", caller);
  console.log("Contract owner:", owner);

  console.log("Sending transaction...");
  const tx = await contract.recordVerification(
  reportId,
  dataHashBytes32,
  category,
  modelVersion
);


  console.log("tx hash:", tx.hash);
  const receipt = await tx.wait();

  console.log("confirmed in block:", receipt.blockNumber);
}

main().catch((e) => {
  // Clean error printing for PHP -> node output
  console.error("❌ verify failed:", e?.shortMessage || e?.message || e);
  process.exit(1);
});
